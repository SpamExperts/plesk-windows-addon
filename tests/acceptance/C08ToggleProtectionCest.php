<?php

use Pages\ConfigurationPage;
use Pages\DomainListPage;
use Pages\ProfessionalSpamFilterPage;
use Step\Acceptance\CommonSteps;

class C08ToggleProtectionCest
{
    public function _before(CommonSteps $I)
    {
        $I->login();
    }

    public function _after(CommonSteps $I)
    {
        $I->removeCreatedSubscriptions();
    }

    public function _failed(CommonSteps $I)
    {
        $this->_after($I);
    }

    public function testToggleProtectionErrorAddedAsAliasNotDomain(CommonSteps $I)
    {
        $setup = $this->setupErrorAddedAsAliasNotDomainScenario($I);
        $aliasDomainName = $setup['alias_domain_name'];

        // Test
        $I->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $I->searchDomainList($aliasDomainName);
        $I->click(DomainListPage::TOGGLE_PROTECTION_LINK);
        $message = "The protection status of $aliasDomainName could not be changed to unprotected because alias domains and subdomains are treated as normal domains and \"$aliasDomainName\" is already added as an alias.";
        $I->waitForText($message, 60);
    }

    public function testHookErrorAddedAsAliasNotDomain(CommonSteps $I)
    {
        $setup = $this->setupErrorAddedAsAliasNotDomainScenario($I);
        $alias = $setup['alias_domain_name'];

        // Test
        $I->apiCheckDomainExists($alias);
        $I->openDomain($setup['domain']);
        $I->removeAliasAsClient($alias);
        $I->apiCheckDomainExists($alias);
    }

    public function testToggleProtectionErrorAddedAsDomainNotAlias(CommonSteps $I)
    {
        $setup = $this->setupErrorAddedAsDomainNotAliasScenario($I);
        $aliasDomainName = $setup['alias_domain_name'];

        // Test
        $I->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $I->searchDomainList($aliasDomainName);
        $I->checkProtectionStatusIs(DomainListPage::STATUS_DOMAIN_IS_PRESENT_IN_THE_FILTER);
        $I->click(DomainListPage::TOGGLE_PROTECTION_LINK);
        $message = "The protection status of $aliasDomainName could not be changed to unprotected because alias domains and subdomains are treated as aliases and \"$aliasDomainName\" is already added as a normal domain.";
        $I->waitForText($message, 60);
    }

    public function testHookErrorAddedAsDomainNotAlias(CommonSteps $I)
    {
        $setup = $this->setupErrorAddedAsDomainNotAliasScenario($I);
        $alias = $setup['alias_domain_name'];

        // Test
        $I->apiCheckDomainExists($alias);
        $I->openDomain($setup['domain']);
        $I->removeAliasAsClient($alias);
        $I->apiCheckDomainExists($alias);
    }

    public function testToggleAsAliasAndUntoggleAlias(CommonSteps $I)
    {
        $I->goToConfigurationPageAndSetOptions([
            ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => true,
            ConfigurationPage::PROCESS_ADDON_OPT => true,
            ConfigurationPage::ADD_ADDON_OPT => true,
        ]);

        list($customerUsername, $customerPassword, $domain) = $I->createCustomer();
        $I->changeCustomerPlan($customerUsername);
        $I->openDomain($domain);

        $alias = $I->addAliasAsClient($domain);
        $I->apiCheckDomainExists($alias);
        $I->assertIsAliasInSpampanel($alias, $domain);

        $I->logout();
        $I->loginAsRoot();
        $I->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $I->searchDomainList($alias);
        $I->click(DomainListPage::TOGGLE_PROTECTION_LINK);
        $I->waitForText("The protection status of $alias has been changed to unprotected", 60);
        $I->checkProtectionStatusIs(DomainListPage::STATUS_DOMAIN_IS_NOT_PRESENT_IN_THE_FILTER);
        $I->apiCheckDomainNotExists($alias);
        $I->assertIsNotAliasInSpampanel($alias, $domain);
    }

    private function setupErrorAddedAsAliasNotDomainScenario(CommonSteps $I)
    {
        // setup
        $I->goToConfigurationPageAndSetOptions([
            ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
            ConfigurationPage::PROCESS_ADDON_OPT => true,
        ]);

        list($customerUsername, $customerPassword, $domain) = $I->createCustomer();
        $I->changeCustomerPlan($customerUsername);
        
        $I->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $I->searchDomainList($domain);
        $I->click(DomainListPage::TOGGLE_PROTECTION_LINK);
        $I->waitForText("The protection status of $domain has been changed to protected", 60);
        $I->checkProtectionStatusIs(DomainListPage::STATUS_DOMAIN_IS_PRESENT_IN_THE_FILTER);

        $I->openDomain($domain);
        $aliasDomainName = $I->addAliasAsClient($domain);

        $I->logout();
        $I->loginAsRoot();
        $I->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $I->searchDomainList($aliasDomainName);
        $I->checkProtectionStatusIs(DomainListPage::STATUS_DOMAIN_IS_NOT_PRESENT_IN_THE_FILTER);
        $I->addDomainAlias($aliasDomainName, $domain);
        $I->checkProtectionStatusIs(DomainListPage::STATUS_DOMAIN_IS_PRESENT_IN_THE_FILTER);

        $I->goToConfigurationPageAndSetOptions([
            ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
            ConfigurationPage::AUTOMATICALLY_DELETE_DOMAINS_OPT => true,
            ConfigurationPage::PROCESS_ADDON_OPT => true,
            ConfigurationPage::ADD_ADDON_OPT => false,
        ]);

        return [
            'alias_domain_name' => $aliasDomainName,
            'customer_username' => $customerUsername,
            'customer_password' => $customerPassword,
            'domain' => $domain
        ];
    }

    private function setupErrorAddedAsDomainNotAliasScenario(CommonSteps $I)
    {
        $I->goToConfigurationPageAndSetOptions([
            ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => true,
            ConfigurationPage::PROCESS_ADDON_OPT=> true,
            ConfigurationPage::ADD_ADDON_OPT => false,
        ]);

        list($customerUsername, $customerPassword, $domain) = $I->createCustomer();
        $I->changeCustomerPlan($customerUsername);
        $I->openDomain($domain);
        $aliasDomainName = $I->addAliasAsClient($domain);

        $I->apiCheckDomainExists($aliasDomainName);

        $I->logout();
        $I->loginAsRoot();
        $I->goToConfigurationPageAndSetOptions([
            ConfigurationPage::ADD_ADDON_OPT => true,
        ]);

        return [
            'alias_domain_name' => $aliasDomainName,
            'customer_username' => $customerUsername,
            'customer_password' => $customerPassword,
            'domain' => $domain
        ];
    }
}