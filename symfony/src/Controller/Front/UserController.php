<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Event\RedirectEvent;
use App\Form\UserType;
use App\Security\Authenticator;
use App\Security\EmailVerifier;
use App\Service\UserService;
use App\Utils\FlashUtils;
use App\Utils\RecaptchaUtils;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * @author yuu2dev
 * updated 2020.08.01
 */
class UserController extends AbstractController {

    /**     
     * 로그인
     * @Route("/signin", name="user_signin", methods={"GET", "POST"})
     * @Template("/front/user/signin.twig")
     * @access public
     * @param AuthenticationUtils $authenticationUtils
     * @param EventDispatcherInterface $eventDispatcher
     * @return array|object
     */
    public function signIn(AuthenticationUtils $authenticationUtils, EventDispatcherInterface $eventDispatcher) {
        
        if ($this->getUser()) {
            return $this->redirectToRoute('blog_article_index');
        }

        $lastAuthenticationError = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();
    
        return [
            'last_username' => $lastUsername, 
            'last_authentication_error' => $lastAuthenticationError
        ];
    }

    /**
     * 로그아웃
     * @Route("/signout", name="user_signout", methods={"GET"}, options={"i18n"=false})
     * @access public
     * @return void
     */
    public function signOut(): void {}

    /**
     * 등록
     * @Route("/signup", name="user_signup", methods={"GET", "POST"})
     * @Template("/front/user/signup.twig")
     * @access public
     * @param Authenticator $authenticator
     * @param EmailVerifier $emailVerifier
     * @param EventDispatcherInterface $eventDispatcher
     * @param FlashUtils $flash
     * @param GuardAuthenticatorHandler $guardAuthenticatorHandler
     * @param Request $request
     * @param RecaptchaUtils $recaptchaUtils
     * @param TranslatorInterface $translator
     * @param UserService $userService
     * @return array|object
     */
    public function signUpForm(
        Authenticator $authenticator, 
        EmailVerifier $emailVerifier, 
        EventDispatcherInterface $eventDispatcher,
        FlashUtils $flash,
        GuardAuthenticatorHandler $guardAuthenticatorHandler, 
        Request $request, 
        RecaptchaUtils $recaptchaUtils, 
        TranslatorInterface $translator, 
        UserService $userService
    ) {
    
        if ($this->getUser()) {
            return $this->redirectToRoute('blog_article_index');
        }
        
        $form = $this->createForm(UserType::class, new User);
        $form->handleRequest($request);

        switch(true) {

        /* FormType 검사 */
        case !($form->isSubmitted() && $form->isValid()): 
            break;
        
        /* Google Recaptcha 검사 */ 
        case !$recaptchaUtils->verifyRecaptcha($request): 
            $flash->failed('flash.front.user.signup.recaptcha.failed'); 
            break;
      
        default:
            
            /** @var User */
            $user          = $form->getData();
            $thumbnail     = $form->get('thumbnail')->getData();
            $thumbnail_src = $userService->saveThumbnail($thumbnail);
            
            $user->setThumbnail($thumbnail_src);
            $userService->saveUser($user);

            /** 메일 인증 */
            $email = $emailVerifier->setVerifyEmail($user->getEmail(), $translator->trans('front.user.signup.sender'));
            $emailVerifier->sendEmailConfirmation('user_signup_verify',  $user, $email);
            
            $guardAuthenticatorHandler->authenticateUserAndHandleSuccess($user, $request, $authenticator, 'main');
            
            $flash->info('flash.front.user.signup.email.confirm');

            return $this->redirectToRoute('blank');
        }

        return [
            'form' => $form->createView()
        ];
    }

    /**
     * 이메일 중복검사
     * @Route("/signup/dupcheck", name="user_signup_dupcheck", methods={"GET", "POST"})
     * @access public
     * @param Request $request
     * @param UserService $userService
     * @return JsonResponse
     */
    public function signUpCheckEmail(Request $request, UserService $userService): JsonResponse {

        $_email = $request->request->get('_email');

        $jsonResponse = new JsonResponse();

        if (is_null($_email)) {
            $jsonResponse->setStatusCode(Response::HTTP_BAD_REQUEST);
        } else {
            $isDuplicated = $userService->isDuplicatedEmail($_email);
            $jsonResponse->setStatusCode(Response::HTTP_OK);
            $jsonResponse->setData([
                'isDuplicated' => $isDuplicated
            ]);
        }
        return $jsonResponse;
    }

    /**
     * 메일 인증
     * @access public
     * @param EmailVerifier $emailVerifier
     * @param Request $request
     * @param FlashUtils $flash
     * @return
     * @Route("/signup/verify", name="user_signup_verify", methods={"GET"})
     */
    public function signUpVerifyEmail(EmailVerifier $emailVerifier, FlashUtils $flash, Request $request) {
    
        try {
            $emailVerifier->handleEmailConfirmation($request, $this->getUser());
            $flash->success('flash.front.user.signup.email.verified');

        } catch (VerifyEmailExceptionInterface $verifyEmailException) {
            
            $flash->failed('flash.front.user.signup.email.invalid');
        }

        return $this->redirectToRoute('blank');
    }
}
