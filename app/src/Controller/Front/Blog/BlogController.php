<?php

namespace App\Controller\Front\Blog;

use App\Form\ArticleCreateType;
use App\Entity\Article;
use App\Service\BlogService;
use App\Service\CategoryService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @author Yuu2
 * @todo 블로그 게시물 검색
 * updated 2020.01.27
 */
class BlogController extends AbstractController {

  /**
   * 블로그 게시물 일람
   * @Route("/blog", name="blog_index", methods={"GET"})
   * @Template("front/Blog/index.twig")
   * @access public
   * @param Request $request
   * @param BlogService $blogService
   * @param CategoryService $categoryService
   * @return array
   */
  public function index(Request $request, BlogService $blogService, CategoryService $categoryService): array {
    
    $categories = $categoryService->hierarachy();
    
    return array(
      'Articles' => $blogService->articles($request),
      'Categories' => $categoryService->categories($categories),
      'RecentArticles' => $blogService->recentArticles(10),
      'Tags' => $blogService->tags()
    );
  }

  /**
   * 블로그 게시물 상세
   * @Route("/blog/{id}", name="blog_show", methods={"GET"})
   * @Template("front/Blog/show.twig")
   * @access public
   * @param Article $article
   * @param BlogService $blogService
   * @param CategoryService $categoryService
   * @return array
   */
  public function show(Article $article, BlogService $blogService, CategoryService $categoryService): array {

    $categories = $categoryService->hierarachy();

    return array(
      'Article' => $article,
      'Categories' => $categoryService->categories($categories),
      'RecentArticles' => $blogService->recentArticles(10),
      'Tags' => $blogService->tags()
    );
  }

  /**
   * 블로그 게시물 작성
   * @Route("/blog/new", name="blog_new", methods={"GET"})
   * @Template("front/blog/form.twig")
   * @access public
   * @param Request $request
   * @param BlogService $blogService
   * @param CategoryService $categoryService
   * @return array
   */
  public function new(Request $request, BlogService $blogService, CategoryService $categoryService): array {
    
    $form = $this->createForm(
      ArticleCreateType::class, new User, array('attr' => array('novalidate' => 'novalidate')
    ));

    $form->handleRequest($request);
    $csrfToken = $request->get('_token'); 

    if($form->isSubmitted() && $form->isValid() && $this->isCsrfTokenValid('article', $csrfToken)) {

    }


    $categories = $categoryService->hierarachy();

    return array(
      'Categories' => $categoryService->categories($categories),
      'RecentArticles' => $blogService->recentArticles(10),
      'Tags' => $blogService->tags()
    );
  }

  /**
   * 블로그 게시물 수정
   * @Route("/blog/edit/{id}", name="blog_edit", methods={"GET"})
   * @Template("front/blog/form.twig")
   * @access public
   * @param Article $article
   * @param BlogService $blogService
   * @return array
   */
  public function edit(Article $article, BlogService $blogService): array {

    return array();
  }

  /**
   * 블로그 게시물 영속화
   * @Route("/blog/save", name="blog_save", methods={"POST"})
   * @access public
   * @param Request $request
   * @param BlogService $blogService
   * @return array
   */
  public function save(Request $request, BlogService $blogService): array {
    
    return array();
  }

  /**
   * 블로그 게시물 삭제
   * @Route("/blog/delete/{id}", name="blog_delete", methods={"DELETE"})
   * @access public
   * @param Request $request
   * @param Article $article
   * @return array
   */
  public function delete(Request $request, Article $article): array {


    return $this->redirectToRoute('blog_index');
  }
}
