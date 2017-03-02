<?php
use \Mockery as m;

class OmiseTest extends PHPUnit_Framework_TestCase
{
    private $checkout_form;
    private $omise;
    private $omise_plugin_helper_charge;
    private $omise_transaction_model;
    private $payment_module;
    private $setting;
    private $smarty;

    public function setup()
    {
        $this->getMockBuilder(get_class(new stdClass()))
            ->setMockClassName('PaymentModule')
            ->setMethods(
                array(
                    '__construct',
                    'display',
                    'displayConfirmation',
                    'install',
                    'l',
                    'registerHook',
                    'uninstall',
                    'unregisterHook',
                )
            )
            ->getMock();

        $this->checkout_form = $this->getMockBuilder(get_class(new CheckoutForm()))
            ->setMethods(
                array(
                    'getListOfExpirationYear',
                )
            )
            ->getMock();

        m::mock('alias:\Configuration')
            ->shouldReceive('get')
            ->shouldReceive('deleteByName');

        $this->omise_plugin_helper_charge = m::mock('alias:\OmisePluginHelperCharge')
            ->shouldReceive('amount')
            ->andReturn(10025)
            ->shouldReceive('isCurrentCurrencyApplicable')
            ->andReturn(true);

        $this->omise_transaction_model = $this->getMockedOmiseTransactionModel();

        $this->setting = $this->getMockBuilder(get_class(new Setting()))
            ->setMethods(
                array(
                    'delete',
                    'getLivePublicKey',
                    'getLiveSecretKey',
                    'getPublicKey',
                    'getSubmitAction',
                    'getTestPublicKey',
                    'getTestSecretKey',
                    'getTitle',
                    'isModuleEnabled',
                    'isSandboxEnabled',
                    'isSubmit',
                    'isThreeDomainSecureEnabled',
                    'save',
                )
            )
            ->getMock();

        $this->smarty = $this->getMockBuilder(get_class(new stdClass()))
            ->setMockClassName('Smarty')
            ->setMethods(
                array(
                    'assign',
                )
            )
            ->getMock();

        $this->omise = new Omise();
        $this->omise->context = $this->getMockedContext();
        $this->omise->setCheckoutForm($this->checkout_form);
        $this->omise->setSetting($this->setting);
        $this->omise->setSmarty($this->smarty);
        $this->omise->setOmiseTransactionModel($this->omise_transaction_model);
    }

    public function testConstructor_whenInitiateTheNewInstance_theDefaultValueOfTheAttributeSettingMustBeAvailable()
    {
        $omise = new Omise();

        $setting = $omise->getSetting();

        $this->assertInstanceOf(get_class(new Setting()), $setting);
    }

    public function testName_theNameThatUsedToReferenceInTheProgramMustBe_omise()
    {
        $this->assertEquals('omise', $this->omise->name);
    }

    public function testDisplayName_theNameThatUsedToDisplayToTheMerchantMustBe_Omise()
    {
        $this->assertEquals('Omise', $this->omise->displayName);
    }

    public function testNeedInstance_configureTheModule_noNeedToLoadModuleAtTheBackendModulePage()
    {
        $this->assertEquals(0, $this->omise->need_instance);
    }

    public function testBootstrap_configureTheModule_bootstrapTemplateIsRequired()
    {
        $this->assertEquals(true, $this->omise->bootstrap);
    }

    public function testGetContent_merchantOpenTheSettingPage_retrieveSettingDataFromTheDatabaseAndDisplayOnThePage()
    {
        $this->setting->method('getLivePublicKey')->willReturn('live_public_key');
        $this->setting->method('getLiveSecretKey')->willReturn('live_secret_key');
        $this->setting->method('isModuleEnabled')->willReturn('module_status');
        $this->setting->method('isSandboxEnabled')->willReturn('sandbox_status');
        $this->setting->method('getSubmitAction')->willReturn('submit_action');
        $this->setting->method('getTestPublicKey')->willReturn('test_public_key');
        $this->setting->method('getTestSecretKey')->willReturn('test_secret_key');
        $this->setting->method('getTitle')->willReturn('title');
        $this->setting->method('isThreeDomainSecureEnabled')->willReturn('three_domain_secure_status');

        $this->smarty->expects($this->once())
            ->method('assign')
            ->with(array(
                'live_public_key' => 'live_public_key',
                'live_secret_key' => 'live_secret_key',
                'module_status' => 'module_status',
                'sandbox_status' => 'sandbox_status',
                'submit_action' => 'submit_action',
                'test_public_key' => 'test_public_key',
                'test_secret_key' => 'test_secret_key',
                'title' => 'title',
                'three_domain_secure_status' => 'three_domain_secure_status',
            ));

        $this->omise->getContent();
    }

    public function testGetContent_merchantSaveSetting_saveTheSettingData()
    {
        $this->setting->method('isSubmit')->willReturn(true);

        $this->setting->expects($this->once())->method('save');

        $this->omise->getContent();
    }

    public function testHookPayment_moduleIsActivatedAndTheSettingOfModuleStatusIsEnabled_displayThePaymentTemplateFile()
    {
        $this->omise->active = true;
        $this->setting->method('isModuleEnabled')->willReturn(true);
        $this->omise->method('display')->willReturn('payment_template_file');

        $this->assertEquals('payment_template_file', $this->omise->hookPayment());
    }

    public function testHookPayment_moduleIsActivatedAndTheSettingOfModuleStatusIsEnabled_displayCheckoutForm()
    {
        $this->omise->active = true;
        $this->setting->method('isModuleEnabled')->willReturn(true);
        $this->setting->method('getPublicKey')->willReturn('omise_public_key');
        $this->setting->method('getTitle')->willReturn('title_at_header_of_checkout_form');
        $this->checkout_form->method('getListOfExpirationYear')->willReturn('list_of_expiration_year');
        $this->omise->context->link->method('getModuleLink')->willReturn('payment');

        $this->smarty->expects($this->exactly(4))
            ->method('assign')
            ->withConsecutive(
                array('action', 'payment'),
                array('list_of_expiration_year', 'list_of_expiration_year'),
                array('omise_public_key', 'omise_public_key'),
                array('omise_title', 'title_at_header_of_checkout_form')
            );

        $this->omise->hookPayment();
    }

    public function testHookPayment_moduleIsActivatedButTheSettingOfModuleStatusIsDisabled_paymentFormMustNotBeDisplayed()
    {
        $this->omise->active = true;
        $this->setting->method('isModuleEnabled')->willReturn(false);

        $this->assertNull($this->omise->hookPayment());
    }

    public function testHookPayment_moduleIsInactivatedButTheSettingOfModuleStatusIsEnabled_paymentFormMustNotBeDisplayed()
    {
        $this->omise->active = false;
        $this->setting->method('isModuleEnabled')->willReturn(true);

        $this->assertNull($this->omise->hookPayment());
    }

    public function testHookPayment_moduleIsInactivatedAndTheSettingOfModuleStatusIsDisabled_paymentFormMustNotBeDisplayed()
    {
        $this->omise->active = false;
        $this->setting->method('isModuleEnabled')->willReturn(false);

        $this->assertNull($this->omise->hookPayment());
    }

    public function testInstall_installationIsSuccess_true()
    {
        $this->omise->method('install')->willReturn(true);
        $this->omise->method('registerHook')->will($this->onConsecutiveCalls(true, true));
        $this->omise_transaction_model->method('createTable')->willReturn(true);

        $this->assertTrue($this->omise->install());
    }

    public function testInstall_createTableIsFail_false()
    {
        $this->omise->method('install')->willReturn(true);
        $this->omise->method('registerHook')->will($this->onConsecutiveCalls(true, true));
        $this->omise_transaction_model->method('createTable')->willReturn(false);

        $this->assertFalse($this->omise->install());
    }

    public function testInstall_parentInstallationIsFail_false()
    {
        $this->omise->method('install')->willReturn(false);
        $this->omise->method('registerHook')->will($this->onConsecutiveCalls(true, true));
        $this->omise_transaction_model->method('createTable')->willReturn(true);

        $this->assertFalse($this->omise->install());
    }

    public function testInstall_registerHookForDisplayOrderConfirmationIsFail_false()
    {
        $this->omise->method('install')->willReturn(true);
        $this->omise->method('registerHook')->will($this->onConsecutiveCalls(false, true));
        $this->omise_transaction_model->method('createTable')->willReturn(true);

        $this->assertFalse($this->omise->install());
    }

    public function testInstall_registerHookForPaymentIsFail_false()
    {
        $this->omise->method('install')->willReturn(true);
        $this->omise->method('registerHook')->will($this->onConsecutiveCalls(true, false));
        $this->omise_transaction_model->method('createTable')->willReturn(true);

        $this->assertFalse($this->omise->install());
    }

    public function testUninstall_uninstallTheModule_theSettingMustBeDeleted()
    {
        $this->setting->expects($this->once())
            ->method('delete');

        $this->omise->uninstall();
    }

    public function testUninstall_uninstallIsSuccess_true()
    {
        $this->omise->method('uninstall')->willReturn(true);
        $this->omise->method('unregisterHook')->will($this->onConsecutiveCalls(true, true));

        $this->assertTrue($this->omise->uninstall());
    }

    public function testUninstall_parentUninstallIsFail_false()
    {
        $this->omise->method('uninstall')->willReturn(false);
        $this->omise->method('unregisterHook')->will($this->onConsecutiveCalls(true, true));

        $this->assertFalse($this->omise->uninstall());
    }

    public function testUninstall_unregisterHookForDisplayOrderConfirmationIsFail_false()
    {
        $this->omise->method('uninstall')->willReturn(true);
        $this->omise->method('unregisterHook')->will($this->onConsecutiveCalls(false, true));

        $this->assertFalse($this->omise->uninstall());
    }

    public function testUninstall_unregisterHookForPaymentIsFail_false()
    {
        $this->omise->method('uninstall')->willReturn(true);
        $this->omise->method('unregisterHook')->will($this->onConsecutiveCalls(true, false));

        $this->assertFalse($this->omise->uninstall());
    }

    private function getMockedContext()
    {
        $currency = $this->getMockBuilder(get_class(new stdClass()));
        $currency->iso_code = 'THB';

        $link = $this->getMockBuilder(get_class(new stdClass()))
            ->setMethods(
                array(
                    'getModuleLink',
                )
            )
            ->getMock();

        $context = $this->getMockBuilder(get_class(new stdClass()))->getMock();
        $context->currency = $currency;
        $context->link = $link;

        return $context;
    }

    private function getMockedOmiseTransactionModel()
    {
        $omise_transaction_model = $this->getMockBuilder(get_class(new stdClass()))
            ->setMockClassName('OmiseTransactionModel')
            ->setMethods(
                array(
                    'add',
                    'createTable',
                )
            )
            ->getMock();

        return $omise_transaction_model;
    }

    public function tearDown()
    {
        m::close();
    }
}
