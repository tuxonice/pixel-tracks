<?php

namespace Acceptance;

use Tests\Support\AcceptanceTester;

class LoginPageCest
{
    public function canAccessToProfileAfterClickMagicLink(AcceptanceTester $I)
    {
        $I->amOnPage('/send-magic-link');
        $I->see('Send me a Magic link');
        $I->fillField('email', 'user@example.com');
        $I->click('Send');
        $I->see('Please verify your mailbox');
        $I->seeInDatabase('users', ['email' => 'user@example.com']);
        $loginKey = $I->grabEntryFromDatabase('users', ['email' => 'user@example.com']);
        $I->amOnPage('/login/' . $loginKey['login_key']);
        $I->see('Upload Track');
    }
}
