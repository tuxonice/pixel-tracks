<?php

namespace Acceptance;

use Tests\Support\AcceptanceTester;

class NotFoundCest
{
    public function accessToNoFoundRoutGetToRequestMagicLinkPageWhenNotAuthenticated(AcceptanceTester $I)
    {
        $I->amOnPage('/some-page');
        $I->see('Send me a Magic link');
    }

    public function accessToNoFoundRoutGetToNotFoundPageWhenAuthenticated(AcceptanceTester $I)
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

        $I->amOnPage('/some-page');
        $I->see('404 Page not found');
    }
}
