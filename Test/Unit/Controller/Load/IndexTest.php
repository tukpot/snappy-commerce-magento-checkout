<?php
declare(strict_types=1);

namespace SnappyCommerce\CartLink\Test\Unit\Controller\Load;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SnappyCommerce\CartLink\Controller\Load\Index;

class IndexTest extends TestCase
{
    private Index $controller;
    private RequestInterface&MockObject $request;
    private Redirect&MockObject $redirect;
    private RedirectFactory&MockObject $redirectFactory;
    private ManagerInterface&MockObject $messageManager;
    private QuoteIdMaskFactory&MockObject $quoteIdMaskFactory;
    private CartRepositoryInterface&MockObject $cartRepository;
    private CheckoutSession&MockObject $checkoutSession;
    private CustomerSession&MockObject $customerSession;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->redirect = $this->createMock(Redirect::class);
        $this->redirectFactory = $this->createMock(RedirectFactory::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->quoteIdMaskFactory = $this->createMock(QuoteIdMaskFactory::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->customerSession = $this->createMock(CustomerSession::class);

        $this->redirectFactory->method('create')->willReturn($this->redirect);
        $this->redirect->method('setPath')->willReturnSelf();

        $this->controller = new Index(
            $this->request,
            $this->redirectFactory,
            $this->messageManager,
            $this->quoteIdMaskFactory,
            $this->cartRepository,
            $this->checkoutSession,
            $this->customerSession
        );
    }

    public function testRedirectsHomeWhenIdParamIsEmpty(): void
    {
        $this->request->method('getParam')->with('id')->willReturn('');
        $this->messageManager->expects($this->once())->method('addErrorMessage');
        $this->redirect->expects($this->once())->method('setPath')->with('')->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirect, $result);
    }

    public function testRedirectsHomeWhenMaskedIdNotFound(): void
    {
        $this->request->method('getParam')->with('id')->willReturn('bad-mask');

        $mask = $this->createMock(QuoteIdMask::class);
        $mask->expects($this->once())->method('load')->with('bad-mask', 'masked_id')->willReturnSelf();
        $mask->method('getQuoteId')->willReturn(null);
        $this->quoteIdMaskFactory->method('create')->willReturn($mask);

        $this->messageManager->expects($this->once())->method('addErrorMessage');
        $this->redirect->expects($this->once())->method('setPath')->with('')->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirect, $result);
    }

    public function testRedirectsHomeWhenQuoteIsInactive(): void
    {
        $this->request->method('getParam')->with('id')->willReturn('valid-mask');

        $mask = $this->createMock(QuoteIdMask::class);
        $mask->expects($this->once())->method('load')->with('valid-mask', 'masked_id')->willReturnSelf();
        $mask->method('getQuoteId')->willReturn(42);
        $this->quoteIdMaskFactory->method('create')->willReturn($mask);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(42);
        $quote->method('getIsActive')->willReturn(false);
        $this->cartRepository->method('get')->with(42)->willReturn($quote);

        $this->messageManager->expects($this->once())->method('addErrorMessage');
        $this->redirect->expects($this->once())->method('setPath')->with('')->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirect, $result);
    }

    public function testSetsQuoteIdInSessionForGuest(): void
    {
        $this->request->method('getParam')->with('id')->willReturn('valid-mask');

        $mask = $this->createMock(QuoteIdMask::class);
        $mask->expects($this->once())->method('load')->with('valid-mask', 'masked_id')->willReturnSelf();
        $mask->method('getQuoteId')->willReturn(42);
        $this->quoteIdMaskFactory->method('create')->willReturn($mask);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(42);
        $quote->method('getIsActive')->willReturn(true);
        $this->cartRepository->method('get')->with(42)->willReturn($quote);

        $this->customerSession->method('isLoggedIn')->willReturn(false);
        $this->checkoutSession->expects($this->once())->method('setQuoteId')->with(42);
        $this->redirect->expects($this->once())->method('setPath')->with('checkout')->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirect, $result);
    }

    public function testMergesCartForLoggedInCustomer(): void
    {
        $this->request->method('getParam')->with('id')->willReturn('valid-mask');

        $mask = $this->createMock(QuoteIdMask::class);
        $mask->expects($this->once())->method('load')->with('valid-mask', 'masked_id')->willReturnSelf();
        $mask->method('getQuoteId')->willReturn(42);
        $this->quoteIdMaskFactory->method('create')->willReturn($mask);

        $guestQuote = $this->createMock(Quote::class);
        $guestQuote->method('getId')->willReturn(42);
        $guestQuote->method('getIsActive')->willReturn(true);
        $this->cartRepository->method('get')->with(42)->willReturn($guestQuote);

        $customerQuote = $this->createMock(Quote::class);
        $customerQuote->method('getId')->willReturn(99);
        $customerQuote->expects($this->once())->method('collectTotals')->willReturnSelf();
        $this->checkoutSession->method('getQuote')->willReturn($customerQuote);

        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $customerQuote->expects($this->once())->method('merge')->with($guestQuote)->willReturnSelf();
        $this->cartRepository->expects($this->once())->method('save')->with($customerQuote);
        $this->redirect->expects($this->once())->method('setPath')->with('checkout')->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirect, $result);
    }

    public function testSkipsMergeWhenCustomerAlreadyOnSameQuote(): void
    {
        $this->request->method('getParam')->with('id')->willReturn('valid-mask');

        $mask = $this->createMock(QuoteIdMask::class);
        $mask->expects($this->once())->method('load')->with('valid-mask', 'masked_id')->willReturnSelf();
        $mask->method('getQuoteId')->willReturn(42);
        $this->quoteIdMaskFactory->method('create')->willReturn($mask);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(42);
        $quote->method('getIsActive')->willReturn(true);
        $this->cartRepository->method('get')->with(42)->willReturn($quote);

        $customerQuote = $this->createMock(Quote::class);
        $customerQuote->method('getId')->willReturn(42);
        $customerQuote->expects($this->never())->method('merge');
        $this->checkoutSession->method('getQuote')->willReturn($customerQuote);

        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->cartRepository->expects($this->never())->method('save');
        $this->redirect->expects($this->once())->method('setPath')->with('checkout')->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirect, $result);
    }

    public function testRedirectsHomeOnException(): void
    {
        $this->request->method('getParam')->with('id')->willReturn('valid-mask');

        $mask = $this->createMock(QuoteIdMask::class);
        $mask->expects($this->once())->method('load')->with('valid-mask', 'masked_id')->willReturnSelf();
        $mask->method('getQuoteId')->willReturn(42);
        $this->quoteIdMaskFactory->method('create')->willReturn($mask);

        $this->cartRepository->method('get')->willThrowException(new \Exception('DB error'));

        $this->messageManager->expects($this->once())->method('addErrorMessage');
        $this->redirect->expects($this->once())->method('setPath')->with('')->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirect, $result);
    }
}
