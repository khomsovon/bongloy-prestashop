<?php
use \Mockery as m;

class PaymentOrderTest extends PHPUnit_Framework_TestCase
{
    private $cart_id = 1234;
    private $cart_order_total = 100.25;
    private $currency_id = 12;
    private $customer_id = 1;
    private $customer_secure_key = 'customerSecureKey';
    private $extra_variables = array();
    private $is_not_needed_rounding_card_order_total = false;
    private $module;
    private $module_display_name = 'Omise';
    private $module_id = '2';
    private $module_current_order = '3';
    private $optional_message = null;
    private $order_state_accepted_payment = 'orderStatusPayment';
    private $order_state_processing_in_progress = 'orderStatusProcessingInProgress';
    private $payment_order;

    public function setup()
    {
        $this->getMockBuilder(get_class(new stdClass()))
            ->setMockClassName('PaymentModule')
            ->getMock();

        $cart = $this->getMockBuilder(get_class(new stdClass()))
            ->setMethods(
                array(
                    'getOrderTotal',
                )
            )
            ->getMock();
        $cart->method('getOrderTotal')
            ->willReturn($this->cart_order_total);
        $cart->id = $this->cart_id;
        $cart->id_customer = $this->customer_id;

        $currency = $this->getMockBuilder(get_class(new stdClass()));
        $currency->id = $this->currency_id;

        $context = $this->getMockBuilder(get_class(new stdClass()));
        $context->cart = $cart;
        $context->currency = $currency;

        m::mock('alias:\Context')
            ->shouldReceive('getContext')
            ->andReturn($context);

        m::mock('alias:\Configuration')
            ->shouldReceive('get')
            ->with('PS_OS_PAYMENT')
            ->andReturn($this->order_state_accepted_payment)
            ->shouldReceive('get')
            ->with('PS_OS_PREPARATION')
            ->andReturn($this->order_state_processing_in_progress);

        $this->module = $this->getMockBuilder(get_class(new stdClass()))
            ->setMethods(
                array(
                    'validateOrder',
                )
            )
            ->getMock();
        $this->module->currentOrder = $this->module_current_order;
        $this->module->displayName = $this->module_display_name;
        $this->module->id = $this->module_id;

        m::mock('alias:\Module')
            ->shouldReceive('getInstanceByName')
            ->andReturn($this->module);

        $this->payment_order = new PaymentOrder();
    }

    public function testSave_saveTheOrder_onlyOneOrderHasBeenSaved()
    {
        $this->module->expects($this->once())
            ->method('validateOrder')
            ->with($this->cart_id,
                $this->order_state_accepted_payment,
                $this->cart_order_total,
                $this->module_display_name,
                $this->optional_message,
                $this->extra_variables,
                $this->currency_id,
                $this->is_not_needed_rounding_card_order_total,
                $this->customer_secure_key
            );

        $this->payment_order->save();
    }

    public function testSaveAsProcessing_saveTheOrder_saveAnOrderWithProcessingInProgressState()
    {
        $this->module->expects($this->once())
            ->method('validateOrder')
            ->with($this->cart_id,
                $this->order_state_processing_in_progress,
                $this->cart_order_total,
                $this->module_display_name,
                $this->optional_message,
                $this->extra_variables,
                $this->currency_id,
                $this->is_not_needed_rounding_card_order_total,
                $this->customer_secure_key
            );

        $this->payment_order->saveAsProcessing();
    }

    public function tearDown()
    {
        m::close();
    }
}

if (! class_exists('Customer')) {
    class Customer
    {
        public $secure_key = 'customerSecureKey';

        public function __construct($customerId)
        {
        }
    }
}
