<?php

namespace App\Admin\Tools\SendMailToUser;

use App\DB\Entity\User\User;
use App\System\Mail\MailerAdapter;
use App\User\UserManager;
use Psr\Log\LoggerInterface;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SendMailToUserController extends CRUDController
{
  public function __construct(
      protected MailerAdapter $mailer,
      protected UserManager $user_manager,
      protected LoggerInterface $logger
  ) {
  }

  public function listAction(Request $request = null): Response
  {
    return $this->renderWithExtraParams('Admin/Tools/send_mail_to_user.html.twig');
  }

  public function sendAction(Request $request): Response
  {
    /** @var User|null $user */
    $user = $this->user_manager->findUserByUsername((string) $request->query->get('username'));
    if (!$user) {
      return new Response('User does not exist');
    }
    $subject = (string) $request->query->get('subject');
    if ('' === $subject) {
      return new Response('Empty subject!');
    }

    $messageText = (string) $request->query->get('message');
    if ('' === $messageText) {
      return new Response('Empty message!');
    }
    $htmlText = str_replace(PHP_EOL, '<br>', $messageText);
    $mailTo = $user->getEmail();
    $this->mailer->send(
      $mailTo,
      $subject,
      'Admin/Tools/Email/simple_message.html.twig',
      ['message' => $htmlText]
    );

    return new Response('OK - message sent');
  }
}
