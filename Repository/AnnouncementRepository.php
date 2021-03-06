<?php

/*
 * This file is part of the Adverts Plugin.
 *
 * (c) Paweł Mikołajczuk <mikolajczuk.private@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @package AHS\AdvertsPluginBundle
 * @author Paweł Mikołajczuk <mikolajczuk.private@gmail.com>
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 */

namespace AHS\AdvertsPluginBundle\Repository;

use Doctrine\ORM\EntityRepository;
use AHS\AdvertsPluginBundle\TemplateList\AnnouncementCriteria;
use Newscoop\ListResult;

/**
 * AnnouncementRepository
 */
class AnnouncementRepository extends EntityRepository
{
    /**
     * Get list for given criteria
     *
     * @param AHS\AdvertsPluginBundle\TemplateList\AnnouncementCriteria $criteria
     *
     * @return Newscoop\ListResult
     */
    public function getListByCriteria(AnnouncementCriteria $criteria, $showResults = true)
    {
        $qb = $this->createQueryBuilder('a');
        $list = new ListResult();

        $qb->select('a, c')
            ->leftJoin('a.category', 'c')
            ->where('a.removed = false');

        if (!empty($criteria->status)) {
            if (count($criteria->status) > 1) {
                $qb->andWhere($qb->expr()->orX('a.is_active = true', 'a.is_active = false'));
            } else {
                $qb->andWhere('a.is_active = :status');
                $qb->setParameter('status', $criteria->status[0] == 'true' ? true : false);
            }
        }

        if (!empty($criteria->type)) {
            if (count($criteria->type) == 1) {
                $qb->andWhere('a.type = :type');
                $qb->setParameter('type', (int) $criteria->type[0]);
            }
        }

        if ($criteria->withImages !== null) {
            if ($criteria->withImages == true) {
                $qb->select('a, c, i');
                $qb->join('a.images', 'i');
            } else {
                $qb->andWhere('a.images IS NULL');
            }
        }

        if ($criteria->user !== null) {
            $qb->leftJoin('a.user', 'u')
                ->andWhere('u.newscoopUserId = :user')
                ->setParameter('user', $criteria->user);
        } else {
            $qb->leftJoin('a.user', 'u');
        }

        if ($criteria->query) {
            $qb->andWhere($qb->expr()->orX("(a.name LIKE :query)", "(a.description LIKE :query)"));
            $qb->setParameter('query', '%' . trim($criteria->query, '%') . '%');
        }

        if ($criteria->category != 'all' && $criteria->category != null) {
            $qb->andWhere('c.id = :category');
            $qb->setParameter('category', $criteria->category);
        }

        foreach ($criteria->perametersOperators as $key => $operator) {
            if ($key !== 'user') {
                $qb->andWhere('a.'.$key.' '.$operator.' :'.$key)
                    ->setParameter($key, $criteria->$key);
            }
        }

        $countQb = clone $qb;
        $list->count = (int) $countQb->select('COUNT(DISTINCT a)')->getQuery()->getSingleScalarResult();

        if ($criteria->firstResult != 0) {
            $qb->setFirstResult($criteria->firstResult);
        }

        if ($criteria->maxResults != 0) {
            $qb->setMaxResults($criteria->maxResults);
        }

        $metadata = $this->getClassMetadata();
        foreach ($criteria->orderBy as $key => $order) {
            if (array_key_exists($key, $metadata->columnNames)) {
                $key = 'a.' . $key;
            }

            $qb->orderBy($key, $order);
        }

        if (!$showResults) {
            return $qb->getQuery();
        }

        $list->items = $qb->getQuery()->getResult();

        return $list;
    }

    /**
     * Get ads count for given criteria
     *
     * @param  array $criteria
     * @return int
     */
    public function countBy(array $criteria = array())
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(a)')
            ->from($this->getEntityName(), 'a');

        foreach ($criteria as $property => $value) {
            if (!is_array($value)) {
                $queryBuilder->andWhere("a.$property = :$property");
            }
        }

        $query = $queryBuilder->getQuery();
        foreach ($criteria as $property => $value) {
            if (!is_array($value)) {
                $query->setParameter($property, $value);
            }
        }

        return (int) $query->getSingleScalarResult();
    }
}
