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
        $I->apiCheckDomainNotExists($account['domain']);
    }

    public function verifyAutomaticallyAddDomainToPsf(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(array(
            ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => true,
            ConfigurationPage::PROCESS_ADDON_OPT => true,
            ConfigurationPage::DO_NOT_PROTECT_REMOTE_DOMAINS_OPT => false,
        ));
        list($customerUsername, $customerPassword, $domain) = $I->createCustomer();
        $I->changeCustomerPlan($customerUsername);
        $I->checkDomainIsPresentInFilter($domain);
        $I->apiCheckDomainExists($domain);

        $I->openDomain($domain);
        $alias = $I->addAliasAsClient($domain);
        $I->apiCheckDomainExists($alias);
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

    public function verifyAutmaticallyDeleteSecondaryDomains(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(array(
            ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => true,
            ConfigurationPage::PROCESS_ADDON_OPT => true,
            ConfigurationPage::DO_NOT_PROTECT_REMOTE_DOMAINS_OPT => false,
            ConfigurationPage::AUTOMATICALLY_DELETE_DOMAINS_OPT => true

        ));

        list($customerUsername, $customerPassword, $domain) = $I->createCustomer();
        $I->changeCustomerPlan($customerUsername);
        $I->checkDomainIsPresentInFilter($domain);
        $I->apiCheckDomainExists($domain);

        $I->openDomain($domain);
        $alias = $I->addAliasAsClient($domain);
        $I->apiCheckDomainExists($alias);

        $I->removeAliasAsClient($alias);
        $I->apiCheckDomainNotExists($alias);
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

    public function verifyAddonDomainsAsNormalDomain(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(
            array(
                ConfigurationPage::PROCESS_ADDON_OPT => true,
            )
        );

        list($customerUsername, $customerPassword, $domain) = $I->createCustomer();
        $I->changeCustomerPlan($customerUsername);
        $I->checkDomainIsPresentInFilter($domain);
        $I->apiCheckDomainExists($domain);

        $I->openDomain($domain);
        $alias = $I->addAliasAsClient($domain);
        $I->apiCheckDomainExists($alias);

        $I->logout();
        $I->loginAsRoot();
        $I->removeSubscription($domain);
        $I->apiCheckDomainNotExists($domain);
        $I->apiCheckDomainNotExists($alias);
        $I->shareIp();
        list($customerUsername, $customerPassword, $domain) = $I->createCustomer();
        $I->changeCustomerPlan($customerUsername);
        $I->logout();
        $I->login($customerUsername, $customerPassword, true);

        $addonDomainName = $I->addAddonDomainAsClient($domain);
        $I->logout();
        $I->login();
        $I->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $I->searchDomainList($addonDomainName);
        $I->see($addonDomainName, DomainListPage::DOMAIN_TABLE);
        $I->apiCheckDomainExists($addonDomainName);
    }

    public function verifyAddonDomainsAsAnAlias(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(array(
            ConfigurationPage::PROCESS_ADDON_OPT => true,
            ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => true,
            ConfigurationPage::ADD_ADDON_OPT => true,
            ConfigurationPage::DO_NOT_PROTECT_REMOTE_DOMAINS_OPT => false
        ));
        $I->shareIp();
        list($customerUsername, $customerPassword, $domain) = $I->createCustomer();
        $I->openDomain($domain);
        $aliasDomain = $I->addAliasAsClient($domain);
        
        $I->logout();
        $I->login();
        $I->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $I->searchDomainList($aliasDomain);
        $I->see($aliasDomain, DomainListPage::DOMAIN_TABLE);
        $I->see("alias", DomainListPage::DOMAIN_TABLE);
    }

    public function verifyAddonDomainsAsAnAliasSubscription(ConfigurationSteps $I)
    {
        $I->setConfigurationOptions(array(
            ConfigurationPage::PROCESS_ADDON_OPT => true,
            ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => true,
            ConfigurationPage::ADD_ADDON_OPT => true,
        ));
        list($customerUsername, $customerPassword, $domain) = $I->createCustomer();
        $I->changeCustomerPlan($customerUsername);
        $I->apiCheckDomainExists($domain);
        $I->openDomain($domain);
        $aliasDomain = $I->addAliasAsClient($domain);
        $I->apiCheckDomainExists($aliasDomain);
        $I->assertIsAliasInSpampanel($aliasDomain, $domain);
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
