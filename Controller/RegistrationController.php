<?php

namespace VisageFour\Bundle\PersonBundle\Controller;

use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use Platypuspie\AnchorcardsBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use FOS\UserBundle\Model\UserInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use VisageFour\Bundle\PersonBundle\Form\UserRegistrationFormType;

class RegistrationController extends Controller
{

    /**
     * @param Request $request
     * @return null|RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     *
     * @Route("/register", name="security_registerUser")
     */
    public function registerAction(Request $request)
    {
        /** @var $formFactory \FOS\UserBundle\Form\Factory\FactoryInterface */
        $formFactory = $this->get('fos_user.registration.form.factory');
        /** @var $dispatcher \Symfony\Component\EventDispatcher\EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');
        /** @var $navigationService \Platypuspie\AnchorcardsBundle\Services\Navigation */
        $navigationService  = $this->container->get('anchorcardsbundle.navigation');
        $navigation         = $navigationService->getNavigation('security_registerUser');
        /** @var $userManager \Platypuspie\AnchorcardsBundle\Services\UserManager */
        $userManager = $this->container->get('anchorcards.user_manager');

        $user = new User();

        $event = new GetResponseUserEvent($user, $request);
        $dispatcher->dispatch(FOSUserEvents::REGISTRATION_INITIALIZE, $event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        $form = $this->createForm('VisageFour\Bundle\PersonBundle\Form\UserRegistrationFormType', $user);
        $form->handleRequest($request);

        if (
            ($this->container->get('kernel')->getEnvironment() == 'dev') &&
            (!($form->isSubmitted()))
        ) {
            UserRegistrationFormType::setDefaultData ($form);
        }

        if ($form->isValid()) {
            $event = new FormEvent($form, $request);
            $dispatcher->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);

            $flashBag = $this->get('session')->getFlashBag();

            // check no other user exists with email address
            if ($userManager->doesUserEmailExist ($user)) {
                $flashBag->set('error', 'A user with the email address: "'. $user->getEmail() .'" already exists.');
            } else {
                $user->setUsername($user->getEmail());
                $userManager->updateUser($user);

                $flashBag->set('success', 'Success. Your account: "'. $user->getEmail() .'" has been created.');

                if (null === $response = $event->getResponse()) {
                    $url = $this->generateUrl('security_registrationComplete');
                    $response = new RedirectResponse($url);
                }

                //$dispatcher->dispatch(FOSUserEvents::REGISTRATION_COMPLETED, new FilterUserResponseEvent($user, $request, $response));

                return $response;
            }
        }

        return $this->render('@Person/Default/registration.html.twig', array(
            'form'          => $form->createView(),
            'navigation'    => $navigation
        ));
    }

    /**
     * @Route("/registrationComplete", name="security_registrationComplete")
     */
    public function RegistrationCompleteAction (Request $request) {
        /** @var $navigationService \Platypuspie\AnchorcardsBundle\Services\Navigation */
        $navigationService  = $this->container->get('anchorcardsbundle.navigation');
        $navigation         = $navigationService->getNavigation('security_registrationComplete');

        return $this->render('@Person/Default/registrationComplete.html.twig', array(
            'navigation'    => $navigation
        ));
    }

    /**
     * Tell the user to check his email provider
     */
    public function checkEmailAction()
    {
        $email = $this->get('session')->get('fos_user_send_confirmation_email/email');
        $this->get('session')->remove('fos_user_send_confirmation_email/email');
        $user = $this->get('fos_user.user_manager')->findUserByEmail($email);

        if (null === $user) {
            throw new NotFoundHttpException(sprintf('The user with email "%s" does not exist', $email));
        }

        return $this->render('FOSUserBundle:Registration:checkEmail.html.twig', array(
            'user' => $user,
        ));
    }

    /**
     * Receive the confirmation token from user email provider, login the user
     */
    public function confirmAction(Request $request, $token)
    {
        /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
        $userManager = $this->get('fos_user.user_manager');

        $user = $userManager->findUserByConfirmationToken($token);

        if (null === $user) {
            throw new NotFoundHttpException(sprintf('The user with confirmation token "%s" does not exist', $token));
        }

        /** @var $dispatcher \Symfony\Component\EventDispatcher\EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');

        $user->setConfirmationToken(null);
        $user->setEnabled(true);

        $event = new GetResponseUserEvent($user, $request);
        $dispatcher->dispatch(FOSUserEvents::REGISTRATION_CONFIRM, $event);

        $userManager->updateUser($user);

        if (null === $response = $event->getResponse()) {
            $url = $this->generateUrl('fos_user_registration_confirmed');
            $response = new RedirectResponse($url);
        }

        $dispatcher->dispatch(FOSUserEvents::REGISTRATION_CONFIRMED, new FilterUserResponseEvent($user, $request, $response));

        return $response;
    }
}
