<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests;

use Mautic\UserBundle\Entity\Permission;
use Mautic\UserBundle\Entity\Role;
use Mautic\UserBundle\Entity\User;
use Mautic\UserBundle\Model\RoleModel;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;

trait NewUserTrait
{
    /**
     * @param array<string,array<string>> $permissions
     */
    private function newUserWithPermission(string $username, string $password, bool $isAdmin=false, array $permissions=[]): User
    {
        $role           = $this->createRole($isAdmin);
        $user           = $this->createUser($role, $password, $username);

        $this->setPermission($user, $permissions, $isAdmin);

        return $user;
    }

    /**
     * @param array<array<string>> $permissions
     */
    private function setPermission(User $user, array $permissions, bool $isAdmin = false): void
    {
        $role = $user->getRole();
        // Delete previous permissions
        $this->em->createQueryBuilder()
            ->delete(Permission::class, 'p')
            ->where('p.bundle = :bundle')
            ->andWhere('p.role = :role_id')
            ->setParameters(['bundle' => 'report', 'role_id' => $role->getId()])
            ->getQuery()
            ->execute();

        // Set new permissions
        $role->setIsAdmin($isAdmin);
        $roleModel = static::getContainer()->get('mautic.user.model.role');
        \assert($roleModel instanceof RoleModel);
        $roleModel->setRolePermissions($role, $permissions);
        $this->em->persist($role);
        $this->em->flush();
    }

    private function createUser(Role $role, string $password='Maut1cR0cks', string $userName='usercompanytags'): User
    {
        $user = new User();
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setUsername($userName);
        $user->setEmail($userName.'@mautic.com');
        $encoderFactory = self::getContainer()->get('security.encoder_factory');
        \assert($encoderFactory instanceof EncoderFactory);
        $encoder = $encoderFactory->getEncoder($user);
        $user->setPassword($encoder->encodePassword($password, null));
        $user->setRole($role);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createRole(bool $isAdmin = false): Role
    {
        $role = new Role();
        $role->setName('Role');
        $role->setIsAdmin($isAdmin);
        $role->setIsPublished(true);

        $this->em->persist($role);
        $this->em->flush();

        return $role;
    }
}
