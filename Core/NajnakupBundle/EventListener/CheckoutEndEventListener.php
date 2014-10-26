<?php
namespace Core\NajnakupBundle\EventListener;

use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Site\ShopBundle\Controller\ShopController;
use Core\NajnakupBundle\Entity\Najnakup;

class CheckoutEndEventListener
{
    protected $container;
    protected $em;

    public function __construct(\Symfony\Component\DependencyInjection\Container $container, \Doctrine\ORM\EntityManager $em)
    {
        $this->container = $container;
        $this->em = $em;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $controller = $event->getController();

        if (!is_array($controller) && !($controller[0] instanceof ShopController)) {
            return;
        }
        $controller = $controller[0]; //ShopController
        $request = $event->getRequest();
        $session = $request->getSession();
        $routename = $request->get('_route');
        if (in_array($routename, array("checkout_end"))) {
            $key = $this->container->getParameter('najnakup.key');
            if (!empty($key)) {
                $cart = $controller->getCart();
                if (!$cart->isEmpty()) {
                    if ($session->has('order-id')) {
                        $order = $session->get('order-id');
                        $em = $this->em;
                        $orderEntity = $em->getRepository('CoreShopBundle:Order')->find($order);
                        if (!empty($orderEntity)) {
                            $overeno = new Najnakup($key);
                            $overeno->setEmail($orderEntity->getInvoiceEmail());
                            $orderitems = $orderEntity->getItems();
                            foreach ($orderitems as $item) {
                                if ($item->getProduct()) {
                                    //$pname = str_replace(array('&nbsp;', '&amp;'), array(" ", "&"), strip_tags($item->getProduct()->getTitle()));
                                    $overeno->addProduct($item->getProduct()->getId());
                                }
                            }
                            $overeno->addOrderId($orderEntity->getId());
                            $overeno->send();
                        }
                    }
                }
            }
        }
    }
}
