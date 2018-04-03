<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ForgotPasswordType;
use App\Form\ResetPasswordType;
use App\Service\UserMail;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends Controller
{
    /**
     * @Route("/security", name="security")
     */
    public function index()
    {
        return $this->render('security/index.html.twig', [
            'controller_name' => 'SecurityController',
        ]);
    }

    /**
     * @Route("/", name="security_connexion", methods={"GET", "POST"})
     * @Security("not is_granted('IS_AUTHENTICATED_FULLY')")

     * @param AuthenticationUtils $authenticationUtils
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function login(AuthenticationUtils $authenticationUtils)
    {
        // On récupère le messagte d'erreur si il y en a un
        $error = $authenticationUtils->getLastAuthenticationError();

        $lastEmail = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig',[
            'last_email' => $lastEmail,
            'error' => $error
        ]);
    }

    /**
     * @Route("/forgot-password",name="security_forgot_password", methods={"GET","POST"})
     * @Security("not is_granted('IS_AUTHENTICATED_FULLY')")
     *
     * @param Request $request
     * @param UserMail $mail
     * @param TokenGeneratorInterface $tokenGenerator
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     * @throws \Exception
     */
    public function forgotPassword(Request $request,UserMail $mail,TokenGeneratorInterface $tokenGenerator)
    {
        # Création d'un nouvel utilisateur
        if($request->isMethod('post')) {
            $user = $this
                ->getDoctrine()
                ->getRepository(User::class)
                ->findOneBy(['email' => $request->request->get('forgot_password')['email']]);
        } else {
            $user = new User();
        }

        # Formulaire de mot de passe oublié
        $form = $this->createForm(ForgotPasswordType::class,$user);

        $form->handleRequest($request);

        if($form->isSubmitted()) {
            if($user) {
                $date = new \DateTime();

                $user->setTokenResetPassword($tokenGenerator->generateToken());
                $user->setTokenExpire($date->add(new \DateInterval('P1D')));

                $em = $this->getDoctrine()->getManager();
                $em->persist($user);
                $em->flush();

                $mail->sendResetPassword($user);

                $this->addFlash('success' , 'Un email a été envoyé');
            } else {
                $this->addFlash('danger' , 'Email invalide !');
            }

            return $this->redirectToRoute('security_forgot_password');
        }

        return $this->render('security/forgot-password.html.twig',[
            'form' => $form->createView()
        ]);
    }

    /**
     * Seul les utilisateurs non authentifié peuvent avoir accès a leur reset
     * @Route("/reset-password/{token}", name="security_reset_password", requirements={"token"}, methods={"GET|POST"})
     * @Security("not is_granted('IS_AUTHENTICATED_FULLY')")
     *
     * @param Request $request
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function resetPassword(Request $request,UserPasswordEncoderInterface $passwordEncoder)
    {
        # On va chercher l'utilisateur en fonction de son token
        $user = $this
            ->getDoctrine()
            ->getRepository(User::class)
            ->findOneBy(['tokenResetPassword' => $request->get('token')]);
        # Si il n'y a pas d'utilisateur c'est qu'il n'a pas fait de demande de mot de passe
        # Ou de le token n'est plus valide dans la periode voulu
        if (!$user || ($user->getTokenExpire()->format('Y-m-d H:i:s') < date("Y-m-d H:i:s"))) {
            throw new \InvalidArgumentException("Votre token de remise à jour de votre mot de passe est incorrect.");
        }

        $form = $this->createForm(ResetPasswordType::class,$user);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $form->getData();
            $password = $passwordEncoder->encodePassword($user,$user->getPassword());
            $user->setPassword($password);
            # Pur eviter qu'il est a repasser par ici
            $user->setTokenResetPassword(null);
            # On persiste en base
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();
            $this->addFlash('success' , 'Votre mot de passe à bien été  mit à jour.');
            return $this->redirectToRoute('security_connexion');
        }

        return $this->render('security/reset-password.html.twig',[
            'form' => $form->createView()
        ]);
    }
}
