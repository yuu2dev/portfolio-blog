<?php

namespace App\Repository;

use App\Entity\Article;
use App\Util\CustomValidator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @author yuu2dev
 * updated 2020.07.12
 *
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends ServiceEntityRepository {

  /**
   * @access public
   * @param ManagerRegistry $registry
   */
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Article::class);
  }

  /**
   * @todo 데이터만 취득하게금 하며, 페이징은 서비스 클래스 로 이동
   * 게시글 일람 쿼리
   * @access public
   * @param array $query
   * @param int $count 페이지당 게시글 수
   * @return Object
   */
  public function paging(array $params): ?Object {
    
    $category = $params['category'];
    $tag      = $params['tag'];
    $search   = $params['search'];

    $category = is_numeric($category) ? $category : NULL;

    $query = $this->createQueryBuilder('a');
    
    switch(true) {
      // 카테고리
      case $category:
        $query
          ->innerJoin('a.category', 'c')
          ->andWhere('c.id = :category_id')
          ->andWhere('a.visible = :visible')
          ->andWhere('c.visible = :visible')
          ->setParameter('category_id', $category)
          ->setParameter('visible', true);
      break;
      // 태그
      case $tag:
        $query
          ->innerJoin('a.tag', 't')
          ->where('t.name = :name')
          ->setParameter('name', $tag);
      break;
      // 검색
      case $search:
        foreach($this->prepareQuery($search) as $key => $term) {
          $query
          ->orWhere('a.title LIKE :title_' . $key)
          ->orWhere('a.content LIKE :content_' . $key)
          ->setParameter('title_' . $key, '%' . trim($term) . '%')
          ->setParameter('content_' . $key, '%' . trim($term) . '%');
        }
      break;
    }

    $query
      ->andWhere('a.visible = :visible')
      ->setParameter('visible', true)
      ->orderBy('a.id', 'DESC')
      ->getQuery()
    ;
  
    return $query;
  }

  /**
   * @todo 관리자일 경우 열람가능 처리
   * 블로그 게시글 상세
   * @access public
   * @param int $id
   * @return
   */
  public function findArticleById(int $id) {
    
    return $this->createQueryBuilder('a')
      ->select('a')
      ->where('a.id = :id')
      ->andWhere('a.visible  = :visible')
      ->setParameter('id', $id)
      ->setParameter('visible', true)
      ->getQuery()
      ->getOneOrNullResult();
  }

  /**
   * 최근 작성한 게시물
   * @access public
   * @param int $count
   * @return array
   */
  public function recentArticles(int $count): ?array {
    return $this->createQueryBuilder('a')
      ->innerJoin('a.category', 'c')
      ->andWhere('c.visible = :visible')
      ->setParameter('visible', true)
      ->addOrderBy('a.updated_at', 'DESC')
      ->addOrderBy('a.created_at', 'DESC')
      ->addOrderBy('a.id', 'DESC')
      ->getQuery()
      ->setMaxResults($count)
      ->getResult();
  }
  
  /**
   * @access public
   * @return string
   */
  public function countArticles(): ?string {
    return $this->createQueryBuilder('a')
      ->select('count(a.id)')
      ->innerJoin('a.category', 'c')
      ->andWhere('c.visible = :visible')
      ->setParameter('visible', true)
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * 검색 문자열 처리
   * @access private
   * @param string $search
   * @return array
   */
  private function prepareQuery(string $search): array {
    $terms = array_unique(explode(' ', $search));
    return array_filter($terms, function($term) {
      return 2 <= mb_strlen($term);
    }); 
  }
}
