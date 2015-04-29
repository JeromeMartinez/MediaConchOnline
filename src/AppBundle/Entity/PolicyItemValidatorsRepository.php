<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * PolicyItemValidatorsRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class PolicyItemValidatorsRepository extends EntityRepository
{
    public function findAllValidatorsByField($field)
    {
        return $this->getEntityManager()
            ->createQuery(
                'SELECT v FROM AppBundle:PolicyItemValidators v WHERE (v.field = :field OR v.field IS NULL)'
            )
            ->setParameter('field', $field)
            ->getResult();
    }
}
