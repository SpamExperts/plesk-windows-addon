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
        $I->see(UpdatePage::DESCRIPTION_A);
        $I->see(UpdatePage::DESCRIPTION_B);
    }

    public function submitUpgradeForm()
    {
        $this->click('Click to upgrade');
    }

    public function checkNoticeAfterUpgrade()
    {
        $this->see('There is no stable update available to install. You are already at the latest version.');
    }
}
