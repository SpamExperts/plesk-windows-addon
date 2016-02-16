<?php

use Pages\UpdatePage;
use Pages\ProfessionalSpamFilterPage;
use \Step\Acceptance\UpdateSteps;

/**
 * @group pleskWindows
 */
class C06UpdateCest
{
    public function _before(UpdateSteps $I)
    {
        $I->login();
    }

    public function _after(UpdateSteps $I)
    {
    }

    public function verifyUpdatePage(UpdateSteps $I)
    {
        $I->goToPage(ProfessionalSpamFilterPage::UPDATE_BTN, UpdatePage::TITLE);
        $I->checkUpdatePageLayout();
    }
}
