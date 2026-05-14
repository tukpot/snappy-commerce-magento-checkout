<?php
declare(strict_types=1);

namespace SnappyCommerce\CartLink\Controller\Load;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly QuoteIdMaskFactory $quoteIdMaskFactory,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession
    ) {}

    public function execute(): Redirect
    {
        $maskedId = (string) $this->request->getParam('id');
        $redirect = $this->redirectFactory->create();

        if ($maskedId === '') {
            $this->messageManager->addErrorMessage(__('Invalid cart link.'));
            return $redirect->setPath('');
        }

        try {
            $mask = $this->quoteIdMaskFactory->create()->load($maskedId, 'masked_id');

            if (!$mask->getQuoteId()) {
                $this->messageManager->addErrorMessage(__('Cart not found.'));
                return $redirect->setPath('');
            }

            $quote = $this->cartRepository->get((int) $mask->getQuoteId());

            if (!$quote->getIsActive()) {
                $this->messageManager->addErrorMessage(__('This cart is no longer available.'));
                return $redirect->setPath('');
            }

            if ($this->customerSession->isLoggedIn()) {
                $customerQuote = $this->checkoutSession->getQuote();
                if ((int) $customerQuote->getId() !== (int) $quote->getId()) {
                    $customerQuote->merge($quote)->collectTotals();
                    $this->cartRepository->save($customerQuote);
                }
            } else {
                $this->checkoutSession->setQuoteId($quote->getId());
            }

            return $redirect->setPath('checkout');
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('Cart not found.'));
            return $redirect->setPath('');
        } catch (\Exception) {
            $this->messageManager->addErrorMessage(__('Unable to load cart. Please try again.'));
            return $redirect->setPath('');
        }
    }
}
