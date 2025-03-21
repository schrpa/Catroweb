<?php

namespace App\DB\Entity\User;

use App\DB\EntityRepository\User\ResetPasswordRequestRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

/**
 * @ORM\Entity(repositoryClass=ResetPasswordRequestRepository::class)
 * @ORM\Table(name="reset_password_request")
 */
class ResetPasswordRequest implements ResetPasswordRequestInterface
{
  use ResetPasswordRequestTrait;

  /**
   * @ORM\Id
   * @ORM\GeneratedValue
   * @ORM\Column(type="integer")
   */
  private ?int $id = null;

  public function __construct(
        /**
         * @ORM\ManyToOne(
         *     targetEntity=User::class,
         *     inversedBy="reset_password_requests"
         * )
         * @ORM\JoinColumn(nullable=false)
         */
        private User $user,
        DateTimeInterface $expiresAt,
        string $selector,
        string $hashedToken
    ) {
    $this->initialize($expiresAt, $selector, $hashedToken);
  }

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getUser(): object
  {
    return $this->user;
  }
}
