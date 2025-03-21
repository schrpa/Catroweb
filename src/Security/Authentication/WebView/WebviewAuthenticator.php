<?php

namespace App\Security\Authentication\WebView;

use App\DB\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class WebviewAuthenticator.
 *
 * @deprecated
 */
class WebviewAuthenticator extends AbstractAuthenticator
{
  /**
   * @required request cookie CATRO_LOGIN_TOKEN to automatically log in a user in the webview
   *
   *  Must be sent as cookie containing the user token
   *  Must not be empty
   *
   * @var string
   */
  private const COOKIE_TOKEN_KEY = 'CATRO_LOGIN_TOKEN';

  public function __construct(
      private readonly EntityManagerInterface $em,
      protected TranslatorInterface $translator,
      protected RequestStack $request_stack,
      protected LoggerInterface $logger,
      protected UrlGeneratorInterface $url_generator
  ) {
  }

  /**
   * Called on every request to decide if this authenticator should be
   * used for the request. Returning false will cause this authenticator
   * to be skipped.
   *
   * {@inheritdoc}
   */
  public function supports(Request $request): ?bool
  {
    $this->request_stack->getSession()->set('webview-auth', false);

    return $this->hasValidTokenCookieSet($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request): Passport
  {
    $token = $request->cookies->get(self::COOKIE_TOKEN_KEY);

    if (null === $token || '' === $token) {
      throw new AuthenticationException('Empty token!');
    }

    /** @var User|null $user */
    $user = $this->em->getRepository(User::class)
      ->findOneBy(['upload_token' => $token])
        ;

    if (null === $user) {
      throw new AuthenticationException('User not found!');
    }

    return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier()));
  }

  /**
   * {@inheritdoc}
   */
  public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
  {
    $this->request_stack->getSession()->set('webview-auth', true);

    // on success, let the request continue
    return null;
  }

  /**
   * @throws HttpException
   *
   * {@inheritdoc}
   */
  public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
  {
    throw new HttpException(Response::HTTP_UNAUTHORIZED, $exception->getMessage(), null, [], Response::HTTP_UNAUTHORIZED);
  }

  private function hasValidTokenCookieSet(Request $request): bool
  {
    return $request->cookies->has(self::COOKIE_TOKEN_KEY) && '' !== $request->cookies->get(self::COOKIE_TOKEN_KEY);
  }
}
