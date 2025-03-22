<?php

namespace Acceptance;

use Tests\Support\AcceptanceTester;

class UploadTrackCest
{
    public function canUploadTrackFile(AcceptanceTester $I)
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

        $I->fillField('trackName', 'My track');
        // file is stored in 'tests/_data/prices.xls'
        $I->attachFile('input[id="trackFile"]', 'sample.gpx');
        $I->click('Submit');
        $I->see('New file uploaded');
    }
}
