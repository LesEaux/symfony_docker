<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManagerInterface;

final class AccountManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(User $user): void
    {
        $this->change($user, false);
    }

    public function delete(User $user): void
    {
        $this->change($user, true);
    }

    private function change(User $user, bool $delete): void
    {
        $db = $this->em->getConnection();
        $db->beginTransaction();
        try {
            // Read authoritative roles under a lock, including changes since the form was opened.
            $suffix = $db->getDatabasePlatform() instanceof SqlitePlatform ? '' : ' FOR UPDATE';
            $ids = array_map(intval(...), $db->fetchFirstColumn(
                'SELECT id FROM user WHERE is_active = 1 AND roles LIKE ? ORDER BY id'.$suffix,
                ['%"ROLE_ADMIN"%'],
            ));
            if (in_array($user->getId(), $ids, true) && ($delete || !$user->isActiveAdmin())) {
                $others = array_filter($ids, static fn ($id): bool => (int) $id !== $user->getId());
                if ($others === []) {
                    throw new \DomainException('Le dernier administrateur actif ne peut pas être supprimé, désactivé ou rétrogradé.');
                }
            }
            if ($delete) {
                $this->em->remove($user);
            }
            $this->em->flush();
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
