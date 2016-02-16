<?php

use Pages\DomainListPage;
use Pages\ConfigurationPage;
use Pages\ProfessionalSpamFilterPage;
use Step\Acceptance\ConfigurationSteps;


class C03DomainListCest
{
    protected $doamin;

    public function _before(ConfigurationSteps $I)
    {
        $I->login();
    }

    public function _after(ConfigurationSteps $I)
    {
    }

    public function verifyDomainListAsRoot(ConfigurationSteps $I)
    {

        $I->goToPage(ProfessionalSpamFilterPage::CONFIGURATION_BTN, ConfigurationPage::TITLE);

        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
                ConfigurationPage::AUTOMATICALLY_DELETE_DOMAINS_OPT => true,
            )
        );
        $account = $I->addNewSubscription();

        $I->checkDomainList($account['domain'], true);
        $I->checkListDomainsPageLayout();
        $I->checkToggleProtection($account['domain']);
        $I->checkLoginFunctionality($account['domain']);
    }

    public function verifyDomainListAsReseller(ConfigurationSteps $I)
    {
        $I->goToPage(ProfessionalSpamFilterPage::CONFIGURATION_BTN, ConfigurationPage::TITLE);

        $I->setConfigurationOptions(
            array(
                ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => false,
                ConfigurationPage::AUTOMATICALLY_DELETE_DOMAINS_OPT => true,
            )
        );

        list($resellerUsername, $resellerPassword, $resellerId) = $I->createReseller();
        $I->shareIp($resellerId);
        $I->logout();

        $I->login($resellerUsername, $resellerPassword);
        $I->checkPsfPresentForReseller();
        $account = $I->addNewSubscription();

        $I->checkDomainList($account['domain'], false);
        $I->checkToggleProtection($account['domain']);
        $I->checkLoginFunctionality($account['domain']);
    }

    public function verifyDomainListAsCustomer(ConfigurationSteps $I)
    {
        list($customerUsername, $customerPassword, $domain) = $I->createCustomer();
        $I->shareIp();
        $I->logout();
        $I->login($customerUsername, $customerPassword, true);
        $I->checkPsfPresentForCustomer();
        $I->checkLoginFunctionality($domain, false);
    }
}
