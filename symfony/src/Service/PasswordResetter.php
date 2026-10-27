<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PasswordResetter
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $hasher,
        private UrlGeneratorInterface $urls,
        private LoggerInterface $logger,
        #[Autowire('%env(APP_URL)%')] private string $appUrl,
        #[Autowire('%env(MAILER_FROM)%')] private string $sender,
        #[Autowire('%env(APP_NAME)%')] private string $appName,
    ) {
    }

    public function request(string $email): void
    {
        $user = $this->users->findOneBy(['email' => $email, 'isActive' => true]);
        $now = new \DateTimeImmutable();
        if (!$user || $user->getPasswordResetRequestedAt() > $now->modify('-5 minutes')) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $user->requestPasswordReset(hash('sha256', $token), $now);
        $this->em->flush();
        // APP_URL is trusted configuration; never build reset links from the request Host header.
        $link = rtrim($this->appUrl, '/').$this->urls->generate('app_reset_password', ['token' => $token]);
        $message = (new Email())->from($this->sender)->to($user->getUserIdentifier())
            ->subject($this->appName.' — Réinitialisation du mot de passe')
            ->text("Pour choisir un nouveau mot de passe, ouvrez ce lien :\n\n".$link."\n\nCe lien expire dans 15 minutes et ne fonctionne qu'une fois. Si vous n'avez pas fait cette demande, ignorez cet email.");
        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $error) {
            // Keep the public response identical without logging the token or the email body.
            $this->logger->error('Password reset email delivery failed.', ['exception_class' => $error::class]);
        }
    }

    public function find(string $token): ?User
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }
        $user = $this->users->findOneBy(['passwordResetToken' => hash('sha256', $token), 'isActive' => true]);

        return $user && $user->getPasswordResetExpiresAt() > new \DateTimeImmutable() ? $user : null;
    }

    public function reset(string $token, string $password): bool
    {
        $user = $this->find($token);
        if (!$user) {
            return false;
        }
        // Conditional update consumes the token atomically, including concurrent submissions.
        $affected = $this->em->getConnection()->executeStatement(
            'UPDATE user SET password = ?, password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = ? AND password_reset_token = ? AND password_reset_expires_at > ? AND is_active = 1',
            [$this->hasher->hashPassword($user, $password), $user->getId(), hash('sha256', $token), new \DateTimeImmutable()],
            [Types::STRING, Types::INTEGER, Types::STRING, Types::DATETIME_IMMUTABLE],
        );
        $this->em->refresh($user);

        return $affected === 1;
    }
}
