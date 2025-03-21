<?php

namespace App\Application\Controller\Comments;

use App\DB\Entity\User\Comment\UserComment;
use App\DB\Entity\User\Notifications\CommentNotification;
use App\DB\Entity\User\User;
use App\Project\ProgramManager;
use App\Translation\TranslationDelegate;
use App\User\Notification\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommentsController extends AbstractController
{
  public function __construct(
        private readonly EntityManagerInterface $entity_manager
    ) {
  }

  /**
   * @throws Exception
   */
  #[Route(path: '/reportComment', name: 'report', methods: ['GET'])]
  public function reportCommentAction(): Response
  {
    $user = $this->getUser();
    if (null === $user) {
      return new Response('', Response::HTTP_UNAUTHORIZED);
    }
    $comment = $this->entity_manager->getRepository(UserComment::class)->find($_GET['CommentId']);
    if (null === $comment) {
      throw $this->createNotFoundException('No comment found for this id '.$_GET['CommentId']);
    }
    $comment->setIsReported(true);
    $this->entity_manager->flush();

    return new Response('', Response::HTTP_OK);
  }

  /**
   * @throws Exception
   */
  #[Route(path: '/deleteComment', name: 'delete', methods: ['GET'])]
  public function deleteCommentAction(): Response
  {
    /** @var User|null $user */
    $user = $this->getUser();
    if (!$user) {
      return new Response('', Response::HTTP_UNAUTHORIZED);
    }
    $comment = $this->entity_manager->getRepository(UserComment::class)->find($_GET['CommentId']);
    if (null === $comment) {
      throw $this->createNotFoundException('No comment found for this id '.$_GET['CommentId']);
    }
    $comment_user_id = 0;
    if ($comment->getUser()) {
      $comment_user_id = $comment->getUser()->getId();
    }
    if ($user->getId() !== $comment_user_id && !$this->isGranted('ROLE_ADMIN')) {
      return new Response('', Response::HTTP_FORBIDDEN);
    }
    $this->entity_manager->remove($comment);
    $this->entity_manager->flush();

    return new Response('', Response::HTTP_OK);
  }

  #[Route(path: '/comment', name: 'comment', methods: ['POST'])]
  public function postCommentAction(NotificationManager $notification_service, ProgramManager $program_manager): Response
  {
    /** @var User|null $user */
    $user = $this->getUser();
    if (null === $user) {
      return new Response('', Response::HTTP_UNAUTHORIZED);
    }
    /** @var User|null $user */
    $user = $this->getUser();
    $program = $program_manager->find($_POST['ProgramId']);
    $temp_comment = new UserComment();
    $temp_comment->setUsername($user->getUserIdentifier());
    $temp_comment->setUser($user);
    $temp_comment->setText($_POST['Message']);
    $temp_comment->setProgram($program);
    $temp_comment->setUploadDate(date_create());
    $temp_comment->setIsReported(false);
    $this->entity_manager->persist($temp_comment);
    $this->entity_manager->flush();
    $this->entity_manager->refresh($temp_comment);
    if ($user !== $program->getUser()) {
      $notification = new CommentNotification($program->getUser(), $temp_comment);
      $notification_service->addNotification($notification);

      // Telling the new comment the CommentNotification it triggered. This is necessary to ensure the
      // correct remove-cascade
      $temp_comment->setNotification($notification);
      $this->entity_manager->persist($temp_comment);
      $this->entity_manager->flush();
    }

    return new Response('', Response::HTTP_OK);
  }

  #[Route(path: '/translate/comment/{id}', name: 'translate_comment', methods: ['GET'])]
  public function translateCommentAction(Request $request, int $id, TranslationDelegate $translation_delegate): Response
  {
    if (!$request->query->has('target_language')) {
      return new Response('Target language is required', Response::HTTP_BAD_REQUEST);
    }
    $comment = $this->entity_manager->getRepository(UserComment::class)->find($id);
    if (null === $comment) {
      return new Response('No comment found for this id', Response::HTTP_NOT_FOUND);
    }
    $source_language = $request->query->get('source_language');
    $source_language = is_null($source_language) ? $source_language : (string) $source_language;
    $target_language = (string) $request->query->get('target_language');
    if ($source_language === $target_language) {
      return new Response('Source and target languages are the same', Response::HTTP_UNPROCESSABLE_ENTITY);
    }
    $response = new JsonResponse();
    $response->setEtag(md5($comment->getText()).$target_language);
    $response->setPublic();
    if ($response->isNotModified($request)) {
      return $response;
    }
    try {
      $translation_result = $translation_delegate->translate($comment->getText(), $source_language, $target_language);
    } catch (InvalidArgumentException $exception) {
      return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
    }
    if (null === $translation_result) {
      return new Response('Translation unavailable', Response::HTTP_SERVICE_UNAVAILABLE);
    }

    return $response->setData([
      'id' => $comment->getId(),
      'source_language' => $source_language ?? $translation_result->detected_source_language,
      'target_language' => $target_language,
      'translation' => $translation_result->translation,
      'provider' => $translation_result->provider,
      '_cache' => null,
    ]);
  }
}
