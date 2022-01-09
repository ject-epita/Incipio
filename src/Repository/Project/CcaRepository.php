<?php

namespace App\Repository\Project;

use App\Entity\Project\Cca;
use Doctrine\ORM\EntityRepository;

class CcaRepository extends EntityRepository
{
    public function findAll()
    {
        return $this->findBy([], ['dateFin' => 'DESC']);
    }

    public function findNotFinished()
    {
        $query = $this->_em->createQueryBuilder();
        $query->select('c')
      ->from(Cca::class, 'c')
      ->where('c.dateFin >= :now')
      ->setParameter('now', new \DateTime('now'));

        return $query;
    }
}
