<?php

namespace Step\Acceptance;

use Pages\UpdatePage;
use Pages\ProfessionalSpamFilterPage;

class UpdateSteps extends CommonSteps
{
    public function checkUpdatePageLayout()
    {
        $I = $this;
        $I->amGoingTo("\n\n --- Gheck update page layout --- \n");

        $I->see(UpdatePage::TITLE, "//h3");
        $I->see(UpdatePage::WIN_DESCRIPTION_A);
        $I->see(UpdatePage::WIN_DESCRIPTION_URL);
        $I->see(UpdatePage::WIN_DESCRIPTION_B);
        $I->see(UpdatePage::WIN_DESCRIPTION_COMMAND);

        $I->seeElement(ProfessionalSpamFilterPage::CONFIGURATION_LINK);
        $I->seeElement(ProfessionalSpamFilterPage::BRANDING_LINK);
        $I->seeElement(ProfessionalSpamFilterPage::DOMAIN_LIST_LINK);
        $I->seeElement(ProfessionalSpamFilterPage::BRANDING_LINK);
        $I->seeElement(ProfessionalSpamFilterPage::MIGRATION_LINK);
        $I->seeElement(ProfessionalSpamFilterPage::UPDATE_LINK);
        $I->seeElement(ProfessionalSpamFilterPage::SUPPORT_LINK);
    }

}
