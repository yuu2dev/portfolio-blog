<?php

namespace App\Controller\Front;

use App\Form\ArticleType;
use App\Form\ArticleCommentType;
use App\Form\ArticleCommentCertType;
use App\Entity\Article;
use App\Entity\ArticleComment;
use App\Entity\Category;
use App\Event\FlashEvent;
use App\Event\RedirectEvent;
use App\Service\BlogService;
use App\Service\CategoryService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author yuu2dev
 * updated 2020.12.24
 */
class BlogController extends AbstractController {

  /**
   * @access public
   */
  public function __construct() {}

  /**
   * 블로그 게시물 일람
   * @Route("/blog", name="blog_article_index", methods={"GET"})
   * @Template("front/blog/index.twig")
   * @access public
   * @param Request $request
   * @param BlogService $blogService
   * @param CategorySerivce $categoryService
   * @return array
   */
  public function article_index(Request $request, BlogService $blogService, CategoryService $categoryService): array {

    return array(
      'Articles' => $blogService->pagingArticles($request),
      'Articles_cnt' => $blogService->countArticles(),
      'Categories' => $categoryService->allCategories(),
      'RecentArticles' => $blogService->recentArticles(10),
      'RecentTags' => $blogService->recentTags(30)
    );
  }

  /**
   * 블로그 게시물 상세
   * @Route("/blog/article/{id}", name="blog_article_show", methods={"GET", "POST"}, requirements={"id":"\d+"})
   * @Entity("article", expr="repository.findArticleById(id)")
   * @Template("front/blog/show.twig")
   * @access public
   * @param Article $article
   * @param BlogService $blogService
   * @param CategorySerivce $categoryService
   * @param EventDispatcherInterface $eventDispatcher
   * @param Request $request
   * @return array
   */
  public function article_show(?Article $article, BlogService $blogService, CategoryService $categoryService, EventDispatcherInterface $eventDispatcher, Request $request) {

    if (!$article) {
      $eventDispatcher->dispatch(FlashEvent::BLOG_ARTICLE_INVISIBLE);
      $eventDispatcher->dispatch(RedirectEvent::REDIRECT, new RedirectEvent);
    }
    
    $form = $this->createForm(ArticleCommentType::class, new ArticleComment, [
      'method' => 'post', 
      'branch' => ArticleCommentType::BRANCH_NEW
    ]);

    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
      /** @var ArticleComment */
      $comment = $form->getData();
      $comment->setArticle($article);
      
      $blogService->writeComment($comment) ? $eventDispatcher->dispatch(FlashEvent::BLOG_ARTICLE_COMMENT_WRITE_SUCCESS) : $eventDispatcher->dispatch(FlashEvent::BLOG_ARTICLE_COMMENT_WRITE_FAILED);
      
      return $this->redirectToRoute('blog_article_show', ['id' => $article->getId()]);
    }
   
    return [
      'Article' => $article,
      'Articles_cnt' => $blogService->countArticles(),
      'Comment_cnt' => $blogService->countCommentsByEntity($article),
      'Categories' => $categoryService->allCategories(),
      'RecentArticles' => $blogService->recentArticles(10),
      'RecentTags' => $blogService->recentTags(30),
      'form' => $form->createView(),
    ];
  }

  /**
   * 블로그 게시물 작성 및 수정
   * @Route("/blog/article/new", name="blog_article_new", methods={"GET", "POST"})
   * @Route("/blog/article/edit/{id}", name="blog_article_edit", methods={"GET", "PUT"}, requirements={"id":"\d+"})
   * @Template("front/blog/form.twig")
   * @access public
   * @param Article $article
   * @param BlogService $blogService
   * @param CategoryService $categoryService
   * @param EventDispatcherInterface $eventDispatcher
   * @param Request $request
   * @param TranslatorInterface $translator
   * @return array|object
   */
  public function article_form(?Article $article, Request $request, BlogService $blogService, CategoryService $categoryService, EventDispatcherInterface $eventDispatcher, TranslatorInterface $translator) {
    
    $form = $article ? $this->createForm(ArticleType::class, $article, ['method' => 'put']) : $this->createForm(ArticleType::class, new Article);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      
      /** @var String */
      $hashtag = $form->get('hashtag')->getData();
      $hashtagForm = $blogService->hashtagStringToArray($hashtag);
    
      /** @var Article */
      $article = $form->getData();
          
      $blogService->writeArticle($article, $hashtagForm) ? $eventDispatcher->dispatch(FlashEvent::BLOG_ARTICLE_WRITE_SUCCESS) : $eventDispatcher->dispatch(FlashEvent::BLOG_ARTICLE_WRITE_FAILED);
      
      return $this->redirectToRoute('blog_article_show', ['id' => $article->getId()]);
    }

    return [
      'form' => $form->createView(),
      'Categories' => $categoryService->allCategories(),
      'RecentArticles' => $blogService->recentArticles(10),
      'RecentTags' => $blogService->recentTags(30)
    ];
  }

  /**
   * @todo 플래시 메시지
   * 블로그 게시물 삭제
   * @Route("/blog/article/del/{id}", name="blog_article_del", methods={"GET"}, requirements={"id":"\d+"})
   * @access public
   * @param Article $article
   * @param BlogService $blogService
   * @param EventDispatcherInterface $eventDispatcher
   * @param Request $request
   * @return object
   */
  public function article_del(Article $article, BlogService $blogService, EventDispatcherInterface $eventDispatcher, Request $request): object {
    $blogService->removeArticle($article);
    return $this->redirectToRoute('blog_article_index');
  }

  /**
   * 블로그 댓글 작성
   * @Route("/blog/comment/new/{id}", name="blog_comment_new", methods={"GET", "POST"}, requirements={"id":"\d+"})
   * @access public
   * @param ArticleComment $parent
   * @param BlogService $blogService
   * @param EventDispatcherInterface $eventDispatcher
   * @param Request $request
   */
  public function comment_new(?ArticleComment $parent, BlogService $blogService, EventDispatcherInterface $eventDispatcher, Request $request) {
    
    $formData = [
      'method' => 'post',
      'action' => $this->generateUrl('blog_comment_new', ['id' => 0]),
      'branch' => ArticleCommentType::BRANCH_NEW,
    ];

    // 부모가 존재 하는 경우
    if ($parent instanceof ArticleComment) {
      $formData['action'] = $this->generateUrl('blog_comment_new', ['id' => $parent->getId()]);
      $formData['attr']   = ['id' => 'modal-form'];
    }

    $form = $this->createForm(ArticleCommentType::class, new ArticleComment, $formData);
    $form->handleRequest($request);
    
    $response = new JsonResponse;
    $responseData = [];

    if ($form->isSubmitted() && $form->isValid()) {

      /** @var ArticleComment */
      $comment = $form->getData();
      dump($comment);

      if ($parent instanceof ArticleComment) {
        $comment->setParent($parent);
      }
   
      $blogService->writeComment($comment) ? $eventDispatcher->dispatch(FlashEvent::BLOG_ARTICLE_COMMENT_WRITE_SUCCESS) : $eventDispatcher->dispatch(FlashEvent::BLOG_ARTICLE_COMMENT_WRITE_FAILED);
    
    } else {
      $responseData['form'] = $this->render('front/blog/comment/form_new.twig', ['form' => $form->createView()])->getContent();
    }

    $response->setData($responseData);

    return $this->getUser() ? $this->redirectToRoute('blog_article_show', ['id' => $article->getId()]) : $response;
  }
  
  /**
   * 블로그 댓글 수정
   * @Route("/blog/comment/edit/{id}", name="blog_comment_edit", methods={"GET", "PUT"}, requirements={"id":"\d+"})
   * @access public
   * @param BlogService $blogService
   * @param EventDispatcherInterface $eventDispatcher
   * @param Request $request
   * @return JsonResponse
   */
  public function comment_edit(ArticleComment $comment, BlogService $blogService, EventDispatcherInterface $eventDispatcher, Request $request) {
    
    $form = $this->createForm(ArticleCommentType::class, $comment, [
      'method' => 'put', 
      'action' => $this->generateUrl('blog_comment_edit', ['id' => $comment->getId()]),
      'branch' => ArticleCommentType::BRANCH_EDIT,
    ]);
    $form->handleRequest($request);

    $response = new JsonResponse;
    $responseData = [];
    
    if ($form->isSubmitted() && $form->isValid()) {
      $session = $request->getSession();
      $comment_cert = $session->get('cert');
      $comment_cert = isset($comment_cert['comment_edit']) ? $comment_cert['comment_edit'] : null;
    
      switch(true)  {
        // 익명유저 : 세션 일치 확인
        case $comment_cert != $comment->getId(): break;
        // 멤버유저 : 작성자 확인
        case $this->getUser() != $comment->getUser(): break;

        default:
        /** @var ArticleComment */
        $comment = $form->getData();
        $blogService->writeComment($comment) ? $eventDispatcher->dispatch(FlashEvent::BLOG_ARTICLE_COMMENT_WRITE_SUCCESS) : $eventDispatcher->dispatch(FlashEvent::BLOG_ARTICLE_COMMENT_WRITE_FAILED);
        /* -------------------- */
        $session->remove('auth');
      } 
    } else {
      $responseData['form'] = $this->render('front/blog/comment/form_edit.twig', ['form' => $form->createView()])->getContent();
    }

    $response->setData($responseData);
    return $response;
  }

  /**
   * 블로그 댓글 삭제
   * @todo 검증 리팩토링
   * @Route("/blog/comment/del/{id}", name="blog_comment_del", methods={"GET","DELETE"}, requirements={"id":"\d+"})
   * @access public
   * @param ArticleComment $comment
   * @param BlogService $blogService
   * @param EventDispatcherInterface $eventDispatcher
   * @param Request $request
   * @return JsonResposnse
   */
  public function comment_del(ArticleComment $comment, BlogService $blogService, EventDispatcherInterface $eventDispatcher, Request $request): JsonResponse {
    
    $session = $request->getSession();
    $comment_cert = $session->get('cert');
    $comment_cert = isset($comment_cert['comment_del']) ? $comment_cert['comment_del'] : null;

    switch(true) {
      // 익명유저 : 세션 일치 확인
      case $comment_cert != $comment->getId(): break;
      // 멤버유저 : 작성자 확인
      case $this->getUser() != $comment->getUser(): break;

      default:
      $blogService->removeComment($comment) ? $eventDispatcher->dispatch(FlashEvent::BLOG_ARTICLE_COMMENT_DEL_SUCCESS) : $eventDispatcher->dispatch(FlashEvent::BLOG_ARTICLE_COMMENT_DEL_FAILED);
        
      $session->remove('auth');
    }
    
    $response = new JsonResponse;

    return $this->getUser() ? $this->redirectToRoute('blog_article_show', ['id' => $article->getId()]) : $response;
  }

  /**
   * 블로그 댓글 검증
   * @Route("/blog/comment/cert/{branch}/{id}", name="blog_comment_cert", methods={"GET","POST"}, requirements={"id":"\d+", "branch":"edit|del"})
   * @param ArticleComment $comment
   * @param BlogService $blogService
   * @param EventDispatcherInterface $eventDispatcher
   * @param Request $request
   * @param string $branch 
   * @return JsonResponse
   */
  public function comment_cert(ArticleComment $comment, BlogService $blogService, EventDispatcherInterface $eventDispatcher, Request $request, string $branch = null) {

    $form = $this->createForm(ArticleCommentCertType::class, new ArticleComment, [
      'method'  => 'post', 
      'action'  => $this->generateUrl('blog_comment_cert', ['id' => $comment->getId(), 'branch' => $branch]),
      'comment' => $comment      
    ]);
    $form->handleRequest($request);
    
    $session = $request->getSession();

    $response = new JsonResponse;
    $responseData = [];
    
    if ($form->isSubmitted() && $form->isValid()) {
      
      switch(strtoupper($branch)) {
        // 삭제
        case ArticleCommentType::BRANCH_DEL:
          $session->set('cert', ['comment_del'  => $comment->getId()]);
          return $this->redirect($this->generateUrl('blog_comment_del', ['id' => $comment->getId()])); 
        // 수정
        case ArticleCommentType::BRANCH_EDIT:
          $session->set('cert', ['comment_edit' => $comment->getId()]);
          return $this->redirect($this->generateUrl('blog_comment_edit', ['id' => $comment->getId()]));
        // 유효하지 않은 브랜치
        default: $response->setStatusCode(Response::HTTP_BAD_REQUEST);
      }

    } else {
      $session->remove('auth');
      $responseData['form'] =  $this->render('front/blog/comment/form_cert.twig', ['form' => $form->createView()])->getContent();
    }
    
    $response->setData($responseData);
    return $response;
  }
}
