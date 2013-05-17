<?php
/*
 * This file is part of the BaseApi package.
 *
 * (c) Juan Manuel Torres <kinojman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Onema\BaseApiBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @author  Juan Manuel Torres <kinojman@gmail.com>
 */
class ProductRepository extends EntityRepository
{
    const MAX_LIMIT = 50;
    
    public function findAllOrderByName()
    {
        $em = $this->getEntityManager();
        $queryBuilder = $em->createQueryBuilder('product');
        $products = $queryBuilder->select('product')
            ->from('BaseApiBundle:Product', 'product')
            ->orderBy('product.name', 'DESC')
            ->getQuery()
            ->getResult();
        return $products;
    }
    
    public function findOneByIdJoinedToCategory($id)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery('
            SELECT p, c FROM BaseApiBundle:Product p
            JOIN p.category c
            WHERE p.id = :id'
        )->setParameter('id', $id);
        
        try {
            return $query->getSingleResult();
        }
        catch(\Doctrine\ORM\NoResultException $e) {
            return null;
        }
    }
    
    
    public function findPaginated($from, $limit)
    {
        $from = isset($from) ? $from : 0;
        $limit   = !empty($limit) ? $limit : self::MAX_LIMIT;
        
        // do not allow 
        if($limit > self::MAX_LIMIT) {
            $limit = self::MAX_LIMIT;
        }
        
        $to = $from + $limit;
        
        $em = $this->getEntityManager();
        $query = $em->createQuery('SELECT p FROM BaseApiBundle:Product p')
            ->setFirstResult($from)
            ->setMaxResults($to);
        
        try {
            $paginator = new Paginator($query, false);
        }
        catch(\Doctrine\ORM\NoResultException $e) {
            return null;
        }
        
        $entities = array();
        
        foreach ($paginator as $entity) {
            $entities[] = $entity;
        }
        
        return $entities;
    }
}
