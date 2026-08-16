<?php
namespace NandoJans\EntityTools\Contract;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\User\UserInterface;
interface ListableRepositoryInterface
{
    public function createListQueryBuilder(?UserInterface $user): QueryBuilder;
}
