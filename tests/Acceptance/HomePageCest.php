<?php

namespace Acceptance;

use Tests\Support\AcceptanceTester;

class HomePageCest
{
    public function canAccessHomePage(AcceptanceTester $I)
    {
        $I->amOnPage('/');
        $I->see('Send me a Magic link');
    }

    public function unauthenticatedUsersCanNotAccessProfilePage(AcceptanceTester $I)
    {
        $I->amOnPage('/profile');
        $I->see('Send me a Magic link');
    }
}
