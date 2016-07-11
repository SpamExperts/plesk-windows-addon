<?php

use Pages\DomainListPage;
use Pages\ConfigurationPage;
use Pages\ProfessionalSpamFilterPage;
use Step\Acceptance\ConfigurationSteps;

class C01ConfigurationCest
{
    public function _before(ConfigurationSteps $I)
    {
        $I->login();
        $I->goToPage(ProfessionalSpamFilterPage::CONFIGURATION_BTN, ConfigurationPage::TITLE);
    }

    public function _after(ConfigurationSteps $I)
    {
    }

    public function _failed(ConfigurationSteps $I)
    {
        $this->_after($I);
    }

    public function verifyConfigurationPage(ConfigurationSteps $I)
    {
        $I->checkUnsuccessfullConfigurations();
        $I->checkConfigurationPageLayout();
        $I->setFieldApiUrl(PsfConfig::getApiUrl());
        $I->setFieldApiHostname(PsfConfig::getApiHostname());
        $I->setFieldApiUsernameIfEmpty(PsfConfig::getApiUsername());
        $I->setFieldApiPassword(PsfConfig::getApiPassword());
        $I->setFieldPrimaryMX(PsfConfig::getPrimaryMX());
        $I->amGoingTo(PsfConfig::getApiPassword());

        $I->submitSettingForm();
    }

    public function verifyNotAutomaticallyAddDomainToPsf(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
            )
        );
        $account = $I->addNewSubscription();
        $I->checkDomainIsNotPresentInFilter($account['domain']);
    }

    public function verifyAutomaticallyAddDomainToPsf(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => true,
            )
        );
        $account = $I->addNewSubscription();
        $I->checkDomainIsPresentInFilter($account['domain']);

    }

    public function verifyNotAutomaticallyDeleteDomainToPsf(ConfigurationSteps $I)
    {

        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
                ConfigurationPage::AUTOMATICALLY_DELETE_DOMAINS_OPT => false,
            )
        );

        $account = $I->addNewSubscription();
        $I->toggleProtection($account['domain']);
        $I->apiCheckDomainExists($account['domain']);
        $I->removeSubscription($account['domain']);
        $I->apiCheckDomainExists($account['domain']);
    }

    public function verifyAutomaticallyDeleteDomainToPsf(ConfigurationSteps $I)
    {

        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
                ConfigurationPage::AUTOMATICALLY_DELETE_DOMAINS_OPT => true,
            )
        );

        $account = $I->addNewSubscription();
        $I->toggleProtection($account['domain']);
        $I->apiCheckDomainExists($account['domain']);
        $I->removeSubscription($account['domain']);
        $I->apiCheckDomainNotExists($account['domain']);
    }

    public function verifyNotAutomaticallyChangeMXRecords(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
                ConfigurationPage::AUTOMATICALLY_CHANGE_MX_OPT => false,
                ConfigurationPage::USE_EXISTING_MX_OPT => false,
            )
        );

        $account = $I->addNewSubscription();
        $I->toggleProtection($account['domain']);
        $I->openSubscription($account['domain']);
        $I->dontSee(PsfConfig::getPrimaryMX(), "//table[@class='list']");
    }

    public function verifyAutomaticallyChangeMXRecords(ConfigurationSteps $I)
    {

        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
                ConfigurationPage::AUTOMATICALLY_CHANGE_MX_OPT => true,
                ConfigurationPage::USE_EXISTING_MX_OPT => false,
            )
        );

        $account = $I->addNewSubscription();
        $I->toggleProtection($account['domain']);
        $I->openSubscription($account['domain']);
        $I->see(PsfConfig::getPrimaryMX(), "//table[@class='list']");
    }

    public function verifyNotUseExistingMXRecords(ConfigurationSteps $I)
    {

        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
                ConfigurationPage::AUTOMATICALLY_CHANGE_MX_OPT => false,
                ConfigurationPage::USE_EXISTING_MX_OPT => false,
            )
        );

        $account = $I->addNewSubscription();
        $I->toggleProtection($account['domain']);
        $routes = $I->apiGetDomainRoutes($account['domain']);
        $I->assertContains($I->getEnvHostname().'::25', $routes);
    }

    public function verifyUseExistingMXRecords(ConfigurationSteps $I)
    {

        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
                ConfigurationPage::AUTOMATICALLY_CHANGE_MX_OPT => false,
                ConfigurationPage::USE_EXISTING_MX_OPT => true,
            )
        );

        $account = $I->addNewSubscription();
        $I->toggleProtection($account['domain']);
        $routes = $I->apiGetDomainRoutes($account['domain']);
        $I->assertContains("mail.".$account['domain'].'::25', $routes);
    }

    public function verifyNotConfigureTheEmailAddressForThisDomainOption(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
                ConfigurationPage::CONFIGURE_EMAIL_ADDRESS_OPT => false,
            )
        );

        $account = $I->addNewSubscription();
        $I->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $I->searchDomainList($account['domain']);
        $I->toggleProtection($account['domain']);
        $I->loginOnSpampanel($account['domain']);
        $I->click('Domain settings');
        $I->dontSeeInField('#contact_email', 'sysadmin@spamexperts.com');
    }

    public function verifyConfigureTheEmailAddressForThisDomainOption(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
                ConfigurationPage::CONFIGURE_EMAIL_ADDRESS_OPT => true,
            )
        );

        $account = $I->addNewSubscription();
        $I->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $I->searchDomainList($account['domain']);
        $I->toggleProtection($account['domain']);
        $I->loginOnSpampanel($account['domain']);
        $I->click('Domain settings');
        $I->seeInField('#contact_email', 'sysadmin@spamexperts.com');
    }

    public function verifyNotUseIPAsDestinationOption(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
                ConfigurationPage::USE_IP_AS_DESTINATION_OPT => false,
                ConfigurationPage::USE_EXISTING_MX_OPT => true,
            )
        );

        $account = $I->addNewSubscription();
        $I->toggleProtection($account['domain']);
        $routes = $I->apiGetDomainRoutes($account['domain']);
        $I->assertContains("mail.".$account['domain'].'::25', $routes);
    }

    public function verifyUseIPAsDestinationOption(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => true,
                ConfigurationPage::USE_EXISTING_MX_OPT => false,
                ConfigurationPage::USE_IP_AS_DESTINATION_OPT => true,
                ConfigurationPage::FORCE_CHANGE_MX_ROUTE_OPT => true,
            )
        );

        $account = $I->addNewSubscription();
        $ip = gethostbyname($I->getEnvHostname());
        $routes = $I->apiGetDomainRoutes($account['domain']);
        $I->assertContains($ip.'::25', $routes);
    }

    public function verifyAddonDomains(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(
            array(
                ConfigurationPage::PROCESS_ADDON_OPT => true,
            )
        );

        list($customerUsername, $customerPassword, $domain) = $I->createCustomer();
        $I->shareIp();
        $I->logout();
        $I->login($customerUsername, $customerPassword, true);

        $I->click('Add New Domain');
        $I->waitForText('Adding New Domain Name');
        $addonDomainName = 'addon' . $domain;
        $I->fillField("//input[@id='domainName-name']", $addonDomainName);
        $I->click("//button[@name='send']");
        $I->waitForText("The domain $addonDomainName was successfully created.", 1000);
        $I->logout();
        $I->login();
        $I->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $I->searchDomainList($addonDomainName);
        $I->see($addonDomainName, DomainListPage::DOMAIN_TABLE);
    }

    public function verifyAddonDomainsAsAnAlias(ConfigurationSteps $I)
    {
        $I->setProcessAddOnAndParkedDomainsOption();
        $I->setAddOnAsAnAliasOption();
        $I->shareIp();
        list($customerUsername, $customerPassword, $domain) = $I->createCustomer();
//        $I->logout();
//        $I->login($customerUsername, $customerPassword, true);
        $I->openDomain($domain);
        $I->click("//a[contains(@id,'buttonAddDomainAlias')]");
        $I->waitForText('Add a Domain Alias');
        $aliasDomain = 'alias' . $domain;
        $I->fillField("//input[@id='name']", $aliasDomain);
        $I->click("//button[@name='send']");
        $I->waitForText("The domain alias $aliasDomain was created.", 30);
        $I->logout();
        $I->login();
        $I->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $I->searchDomainList($aliasDomain);
        $I->see($aliasDomain, DomainListPage::DOMAIN_TABLE);
        $I->see("alias", DomainListPage::DOMAIN_TABLE);
    }

    public function verifyRedirectBackToPleskUponLogout(ConfigurationSteps $I)
    {
        $I->removeAllDomains();
        $I->goToPage(ProfessionalSpamFilterPage::CONFIGURATION_BTN, ConfigurationPage::TITLE);
        $I->setRedirectBackToPleskOption();

        $account = $I->addNewSubscription();
        $I->searchDomainList($account['domain']);
        $I->loginOnSpampanel($account['domain']);
        $I->logoutFromSpampanel();
        $I->seeInCurrentAbsoluteUrl($I->getEnvHostname());
    }
}
