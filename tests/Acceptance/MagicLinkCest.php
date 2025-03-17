<?php

namespace Acceptance;

use Tests\Support\AcceptanceTester;

class MagicLinkCest
{
    public function canRequestMagicLink(AcceptanceTester $I)
    {
        $I->amOnPage('/send-magic-link');
        $I->see('Send me a Magic link');
        $I->fillField('email', 'user@example.com');
        $I->click('Send');
        $I->see('Please verify your mailbox');
    }
}
