<?php

namespace Acceptance;

use Tests\Support\AcceptanceTester;

class HomePageCest
{
    public function frontpageWorks(AcceptanceTester $I)
    {
        $I->amOnPage('/');
        $I->see('Send me a Magic link');
    }
}
