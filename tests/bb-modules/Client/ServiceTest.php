<?php


namespace Box\Tests\Mod\Client;


use RedBeanPHP\OODBBean;

class ServiceTest extends \PHPUnit_Framework_TestCase {

    public function testgetDi()
    {
        $di = new \Box_Di();
        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);
        $getDi = $service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testapproveClientEmailByHash()
    {
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())
            ->method('getRow')->will($this->returnValue(array('client_id' => 2, 'id' => 1)));

        $database->expects($this->atLeastOnce())->method('exec');

        $di = new \Box_Di();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);
        $result = $clientService->approveClientEmailByHash('');

        $this->assertTrue($result);
    }

    public function testapproveClientEmailByHashException()
    {
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())
            ->method('getRow')->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $this->setExpectedException('\Box_Exception', 'Invalid email confirmation link');
        $clientService->approveClientEmailByHash('');
    }

    public function testgenerateEmailConfirmationLink()
    {
        $model = new \Model_ExtensionMeta();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())
            ->method('dispense')->will($this->returnValue($model));

        $database->expects($this->atLeastOnce())->method('store')
            ->will($this->returnValue(1));

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('url')
            ->will($this->returnValue('boxbilling.com/index.php/client/confirm-email/'));

        $di = new \Box_Di();
        $di['db'] = $database;
        $di['tools'] = $toolsMock;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $client_id = 1;
        $result = $clientService->generateEmailConfirmationLink($client_id);

        $this->assertInternalType('string', $result);
        $this->assertTrue(strpos($result, '/client/confirm-email/') !== false);
    }

    public function testonAfterClientSignUp()
    {
        $eventParams = array (
            'password' => 'testPassword',
            'id' => 1,
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('getParameters')->will($this->returnValue($eventParams));

        $service = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $service->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function() use ($service) {return $service;});
        $di['mod_config'] = $di->protect(function ($name) use($di) {
            return array ('require_email_confirmation' => false);
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);
        $result = $clientService->onAfterClientSignUp($eventMock);

        $this->assertTrue($result);
    }

    public function testRequireEmailConfirmonAfterClientSignUp()
    {
        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventParams = array (
            'password' => 'testPassword',
            'id' => 1,
            'require_email_confirmation' => true,
        );

        $eventMock->expects($this->atLeastOnce())->
            method('getParameters')->will($this->returnValue($eventParams));

        $service = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $service->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->will($this->returnValue(true));

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->setMethods(array('generateEmailConfirmationLink'))->getMock();
        $clientServiceMock->expects($this->atLeastOnce())->
            method('generateEmailConfirmationLink')->will($this->returnValue('Link_string'));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function($serviceName) use ($service, $clientServiceMock) {
            if ($serviceName == 'email'){
                return $service;
            }
            if ($serviceName == 'client'){
                return $clientServiceMock;
            }
        });
        $di['mod_config'] = $di->protect(function ($name) use($di) {
            return array ('require_email_confirmation' => true);
        });
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $clientService = new \Box\Mod\Client\Service();
        $result = $clientService->onAfterClientSignUp($eventMock);

        $this->assertTrue($result);
    }

    public function testExceptiononAfterClientSignUp()
    {
        $eventParams = array (
            'password' => 'testPassword',
            'id' => 1,
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('getParameters')->will($this->returnValue($eventParams));

        $service = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $service->expects($this->atLeastOnce())->
            method('sendTemplate')->will($this->throwException(new \Exception('exception created in unit test')));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function() use ($service) {return $service;});
        $di['mod_config'] = $di->protect(function ($name) use($di) {
            array ('require_email_confirmation' => false);
        });
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);
        $result = $clientService->onAfterClientSignUp($eventMock);

        $this->assertTrue($result);
    }

    public function searchQueryData()
    {
        return array(
            array(array(), 'SELECT c.*', array()),
            array(
                array('id' => 1),
                'c.id = :client_id or c.aid = :alt_client_id',
                array(':client_id' => '', ':alt_client_id' => '')
            ),
            array(
                array('name' => 'test'),
                '(c.first_name LIKE :first_name or c.last_name LIKE :last_name )',
                array(':first_name' => '', ':last_name' => '')
            ),
            array(
                array('email' => 'test@example.com'),
                'c.email LIKE :email',
                array(':email' => 'test@example.com')
            ),
            array(
                array('company' => 'LTD company'),
                'c.company LIKE :company',
                array(':company' => 'LTD company')
            ),
            array(
                array('status' => 'TEST status'),
                'c.status = :status',
                array(':status' => 'TEST status')
            ),
            array(
                array('group_id' => '1'),
                'c.client_group_id = :group_id',
                array(':group_id' => '1')
            ),
            array(
                array('created_at' => '2012-12-12'),
                "DATE_FORMAT(c.created_at, '%Y-%m-%d') = :created_at",
                array(':created_at' => '2012-12-12')
            ),
            array(
                array('date_from' => '2012-12-10'),
                'UNIX_TIMESTAMP(c.created_at) >= :date_from',
                array(':date_from' => '2012-12-10')
            ),
            array(
                array('date_to' => '2012-12-11'),
                'UNIX_TIMESTAMP(c.created_at) <= :date_from',
                array(':date_to' => '2012-12-11')
            ),
            array(
                array('search' => '2'),
                'c.id = :cid or c.aid = :caid',
                array(':cid' => '2', ':caid' => '2'),
            ),
            array(
                array('search' => 'Keyword'),
                "c.company LIKE :s_company OR c.first_name LIKE :s_first_time OR c.last_name LIKE :s_last_name OR c.email LIKE :s_email OR CONCAT(c.first_name,  ' ', c.last_name ) LIKE  :full_name",
                array(':s_company' => 'Keyword',
                      ':s_first_time' => 'Keyword',
                      ':s_last_name' => 'Keyword',
                      ':s_email' => 'Keyword',
                      ':full_name' => 'Keyword',
                ),
            ),

        );
    }

    /**
     * @dataProvider searchQueryData
     */
    public function testgetSearchQuery($data, $expectedStr, $expectedParams)
    {
        $clientService = new \Box\Mod\Client\Service();
        $result = $clientService->getSearchQuery($data);
        $this->assertInternalType('string', $result[0]);
        $this->assertInternalType('array', $result[1]);

        $this->assertTrue(strpos($result[0], $expectedStr) !== false, $result[0]);
        $this->assertTrue(array_diff_key($result[1], $expectedParams) == array());
    }

    public function testgetSearchQueryChangeSelect()
    {
        $data = array();
        $selectStmt = 'c.id, CONCAT(c.first_name, c.last_name) as full_name';
        $clientService = new \Box\Mod\Client\Service();
        $result = $clientService->getSearchQuery($data, $selectStmt);
        $this->assertInternalType('string', $result[0]);
        $this->assertInternalType('array', $result[1]);

        $this->assertTrue(strpos($result[0], $selectStmt) !== false, $result[0]);
    }

    public function testgetPairs()
    {
        $data = array();

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('getAssoc')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $database;


        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);
        $result = $clientService->getPairs($data);
        $this->assertInternalType('array', $result);
    }

    public function testtoSessionArray()
    {
        $expectedArrayKeys = array (
            'id' => 1,
            'email' => 'email@example.com',
            'name' => 'John Smith',
            'role' => 'admin',
        );

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $clientService = new \Box\Mod\Client\Service();
        $result = $clientService->toSessionArray($model);

        $this->assertInternalType('array', $result);
        $this->assertTrue(array_diff_key($result, $expectedArrayKeys) == array());
    }

    public function testemailAreadyRegistered()
    {
        $email = 'test@example.com';
        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('findOne')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->emailAreadyRegistered($email);
        $this->assertInternalType('bool', $result);
    }

    public function testEmailAlreadyRegWithModel()
    {
        $email = 'test@example.com';
        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->email = $email;

        $clientService = new \Box\Mod\Client\Service();

        $result = $clientService->emailAreadyRegistered($email, $model);
        $this->assertInternalType('bool', $result);
        $this->assertEquals(false, $result);
    }

    public function testcanChangeCurrency()
    {
        $currency = 'EUR';
        $model    = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->currency = 'USD';

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('findOne')
            ->will($this->returnValue(null));

        $di       = new \Box_Di();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);
        $result = $clientService->canChangeCurrency($model, $currency);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }

    public function testcanChangeCurrencyModelCurrencyNotSet()
    {
        $currency = 'EUR';
        $model    = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->never())->method('findOne');

        $clientService = new \Box\Mod\Client\Service();

        $result = $clientService->canChangeCurrency($model, $currency);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }

    public function testcanChangeCurrencyIdenticalCurrencies()
    {
        $currency = 'EUR';
        $model    = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->currency = $currency;

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->never())->method('findOne');

        $clientService = new \Box\Mod\Client\Service();

        $result = $clientService->canChangeCurrency($model, $currency);
        $this->assertInternalType('bool', $result);
        $this->assertFalse($result);
    }

    public function testcanChangeCurrencyHasInvoice()
    {
        $currency = 'EUR';
        $model    = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id       = rand(1, 100);
        $model->currency = 'USD';

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->at(0))->method('findOne')
            ->will($this->returnValue($invoiceModel));

        $di       = new \Box_Di();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $this->setExpectedException('\Box_Exception', 'Currency can not be changed. Client already have invoices issued.');
        $clientService->canChangeCurrency($model, $currency);
    }

    public function testcanChangeCurrencyHasOrder()
    {
        $currency = 'EUR';
        $model    = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id       = rand(1, 100);
        $model->currency = 'USD';

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->at(0))->method('findOne')
            ->will($this->returnValue(null));
        $database->expects($this->at(1))->method('findOne')
            ->will($this->returnValue($clientOrderModel));

        $di       = new \Box_Di();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $this->setExpectedException('\Box_Exception', 'Currency can not be changed. Client already have orders.');
        $clientService->canChangeCurrency($model, $currency);
    }

    public function searchBalanceQueryData()
    {
        return array(
            array(array(), 'FROM client_balance as m', array()),
            array(
                array('id' => 1),
                'm.id = :id',
                array(':id' => '1', )
            ),
            array(
                array('client_id' => 1),
                'm.client_id = :client_id',
                array(':client_id' => '1',)
            ),
            array(
                array('date_from' => '2012-12-10'),
                'm.created_at >= :date_from',
                array(':date_from' => '2012-12-10')
            ),
            array(
                array('date_to' => '2012-12-11'),
                'm.created_at <= :date_to',
                array(':date_to' => '2012-12-11')
            ),

        );
    }

    /**
     * @dataProvider searchBalanceQueryData
     */
    public function testgetBalanceSearchQuery($data, $expectedStr, $expectedParams)
    {
        $clientBalanceService = new \Box\Mod\Client\ServiceBalance();
        list ($sql, $params) = $clientBalanceService->getSearchQuery($data);
        $this->assertNotEmpty($sql);
        $this->assertInternalType('string', $sql);
        $this->assertInternalType('array', $params);

        $this->assertTrue(strpos($sql, $expectedStr) !== false, $sql);
        $this->assertTrue(array_diff_key($params, $expectedParams) == array());

    }

    public function testaddFunds()
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());
        $modelClient->currency = 'USD';

        $model = new \Model_ClientBalance();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $amount = '2.22';
        $description = 'test description';

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('dispense')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->addFunds($modelClient, $amount, $description);
        $this->assertTrue($result);
    }


    public function testaddFundsCurrencyNotDefined()
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());

        $amount = '2.22';
        $description = 'test description';

        $clientService = new \Box\Mod\Client\Service();

        $this->setExpectedException('\Box_Exception', 'Define clients currency before adding funds.');
        $clientService->addFunds($modelClient, $amount, $description);

    }


    public function testaddFundsAmountMissing()
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());
        $modelClient->currency = 'USD';

        $amount = null;
        $description = '';

        $clientService = new \Box\Mod\Client\Service();

        $this->setExpectedException('\Box_Exception', 'Funds amount is not valid');
        $clientService->addFunds($modelClient, $amount, $description);
    }


    public function testaddFundsInvalidDescription()
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());
        $modelClient->currency = 'USD';

        $amount = '2.22';
        $description = null;


        $clientService = new \Box\Mod\Client\Service();

        $this->setExpectedException('\Box_Exception', 'Funds description is not valid');
        $result = $clientService->addFunds($modelClient, $amount, $description);
        $this->assertTrue($result);
    }

    public function testgetExpiredPasswordReminders()
    {
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('find')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->getExpiredPasswordReminders();
        $this->assertInternalType('array', $result);
    }

    public function searchHistoryQueryData()
    {
        return array(
            array(array(), 'SELECT ach.*, c.first_name, c.last_name, c.email', array()),
            array(
                array('search' => 'sameValue'),
                'c.first_name LIKE %:first_name% OR c.last_name LIKE %:last_name% OR c.id LIKE :id',
                array(
                    ':first_name' => 'sameValue',
                    ':last_name' => 'sameValue',
                    ':id' => 'sameValue')
            ),
            array(
                array('client_id' => '1'),
                'ach.client_id = :client_id',
                array(':client_id' => '1')
            ),
        );
    }

    /**
     * @dataProvider searchHistoryQueryData
     */
    public function testgetHistorySearchQuery($data, $expectedStr, $expectedParams)
    {
        $clientService = new \Box\Mod\Client\Service();
        list ($sql, $params) = $clientService->getHistorySearchQuery($data);
        $this->assertNotEmpty($sql);
        $this->assertInternalType('string', $sql);
        $this->assertInternalType('array', $params);

        $this->assertTrue(strpos($sql, $expectedStr) !== false, $sql);
        $this->assertTrue(array_diff_key($params, $expectedParams) == array());
    }

    public function testcounter()
    {
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('getAssoc')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->counter();
        $this->assertInternalType('array', $result);

        $expected = array(
            'total' => 0,
            \Model_Client::ACTIVE =>  0,
            \Model_Client::SUSPENDED =>  0,
            \Model_Client::CANCELED =>  0,
        );
    }

    public function testgetGroupPairs()
    {
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('getAssoc')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->getGroupPairs();
        $this->assertInternalType('array', $result);
    }

    public function testclientAlreadyExists()
    {
        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('findOne')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->clientAlreadyExists('email@example.com');
        $this->assertTrue($result);
    }

    public function testgetByLoginDetails()
    {
        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('findOne')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->getByLoginDetails('email@example.com', 'password');
        $this->assertInstanceOf('\Model_Client', $result);
    }

    public function getProvider()
    {
        return array(
            array('id', 1),
            array('email', 'test@email.com'),
        );
    }

    /**
     * @dataProvider getProvider
     */
    public function testget($fieldName, $fieldValue)
    {

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $data = array($fieldName => $fieldValue);
        $result = $service->get($data);
        $this->assertInstanceOf('\Model_Client', $result);
    }

    public function testgetIdOrEmailNotProvided()
    {
        $service = new \Box\Mod\Client\Service();

        $data = array();
        $this->setExpectedException('\Box_Exception', 'Client ID or email is required');
        $service->get($data);
    }

    public function testgetClientNotFound()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $data = array('id' => 0);
        $this->setExpectedException('\Box_Exception', 'Client not found');
        $service->get($data);
    }

    public function testgetClientBalance()
    {
        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')->will($this->returnValue(1.0));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->getClientBalance($model);
        $this->assertInternalType('numeric', $result);

    }

    public function testtoApiArray()
    {
        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->custom_1 = 'custom field';

        $clientGroup = new \Model_ClientGroup();
        $clientGroup->loadBean(new \RedBeanPHP\OODBBean());
        $clientGroup->title = 'Group Title';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')->will($this->returnValue(array()));
        $dbMock->expects($this->atLeastOnce())
            ->method('load')->will($this->returnValue($clientGroup));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')
            ->setMethods(array('getClientBalance'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getClientBalance');

        $serviceMock->setDi($di);

        $result = $serviceMock->toApiArray($model, true, new \Model_Admin());
        $this->assertInternalType('array', $result);

    }

    public function testIsClientTaxableProvider()
    {
        return array(
            array(
                false,
                false,
                false
            ),
            array(
                true,
                true,
                false
            ),
            array(
                true,
                false,
                true
            )
        );
    }

    /**
     * @dataProvider testIsClientTaxableProvider
     */
    public function testIsClientTaxable($getParamValueReturn, $tax_exempt, $expected)
    {
        $service = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->will($this->returnValue($getParamValueReturn));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($service) {
                return $service;
            });

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->tax_exempt = $tax_exempt;

        $result = $service->isClientTaxable($client);
        $this->assertEquals($expected, $result);

    }

    public function testadminCreateClient()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientModel->id = 1;

        $data = array(
            'password' => uniqid(),
            'email' => 'test@unit.vm',
            'first_name' => 'test',
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($clientModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->exactly(2))
            ->method('fire');


        $passwordMock = $this->getMockBuilder('\Box_Password')->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($data['password']);

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();
        $di['password'] = $passwordMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->adminCreateClient($data);
        $this->assertInternalType('int', $result);
    }

    public function testguestCreateClient()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientModel->id = 1;

        $data = array(
            'password' => uniqid(),
            'email' => 'test@unit.vm',
            'first_name' => 'test',
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($clientModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->exactly(2))
            ->method('fire');

        $requestMock = $this->getMockBuilder('\Box_Request')->getMock();
        $ip = '10.10.10.2';
        $requestMock->expects($this->atLeastOnce())
            ->method('getClientAddress')
            ->will($this->returnValue($ip));


        $passwordMock = $this->getMockBuilder('\Box_Password')->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($data['password']);

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();
        $di['request'] = $requestMock;
        $di['password'] = $passwordMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->guestCreateClient($data);
        $this->assertInstanceOf('\Model_Client', $result);
        $this->assertNotEquals($data['password'], $result->pass, 'Password is not hashed');
        $this->assertEquals($ip, $result->ip, 'IP address is not saved while creating client');
    }

    public function testdeleteGroup()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne');
        $dbMock->expects($this->once())
            ->method('trash');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $model = new \Model_ClientGroup();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $result = $service->deleteGroup($model);
        $this->assertTrue($result);
    }

    public function testdeleteGroup_groupHasClients()
    {
        $clientModel = new \Model_Client();
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($clientModel));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $model = new \Model_ClientGroup();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $this->setExpectedException('\Box_Exception', 'Can not remove group with clients');
        $service->deleteGroup($model);
    }

    public function testauthorizeClient_DidntFoundEmail()
    {
        $email = 'example@boxbilling.vm';
        $password = '123456';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('Client')
            ->willReturn(null);

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->authorizeClient($email, $password);
        $this->assertNull($result);
    }

    public function testauthorizeClient()
    {
        $email = 'example@boxbilling.vm';
        $password = '123456';

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('Client')
            ->willReturn($clientModel);

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->expects($this->atLeastOnce())
            ->method('authorizeUser')
            ->with($clientModel,$password)
            ->willReturn($clientModel);

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['auth'] = $authMock;
        $di['mod_config'] = $di->protect(function ($name) use($di) {
            return array ('require_email_confirmation' => false);
        });
        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->authorizeClient($email, $password);
        $this->assertInstanceOf('\Model_Client', $result);
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testauthorizeClientEmailRequiredNotConfirmed()
    {
        $email    = 'example@boxbilling.vm';
        $password = '123456';

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('Client')
            ->willReturn($clientModel);

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->expects($this->never())
            ->method('authorizeUser')
            ->with($clientModel, $password)
            ->willReturn($clientModel);

        $di               = new \Box_Di();
        $di['db']         = $dbMock;
        $di['auth']       = $authMock;
        $di['mod_config'] = $di->protect(function ($name) use ($di) {
            return array('require_email_confirmation' => true);
        });
        $service          = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->authorizeClient($email, $password);
        $this->assertInstanceOf('\Model_Client', $result);
    }


    public function testauthorizeClientEmailRequiredConfirmed()
    {
        $email    = 'example@boxbilling.vm';
        $password = '123456';

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientModel->email_approved = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('Client')
            ->willReturn($clientModel);

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->expects($this->any())
            ->method('authorizeUser')
            ->with($clientModel, $password)
            ->willReturn($clientModel);

        $di               = new \Box_Di();
        $di['db']         = $dbMock;
        $di['auth']       = $authMock;
        $di['mod_config'] = $di->protect(function ($name) use ($di) {
            return array('require_email_confirmation' => true);
        });
        $service          = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->authorizeClient($email, $password);
        $this->assertInstanceOf('\Model_Client', $result);
    }

}
 