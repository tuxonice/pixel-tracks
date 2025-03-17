<?php

namespace Acceptance;

use Tests\Support\AcceptanceTester;

class UploadTrackCest
{
    public function canUploadTrackFileAndSeeInTheList(AcceptanceTester $I)
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
        $I->attachFile('input[id="trackFile"]', 'sample.gpx');
        $I->click('Submit');
        $I->see('New file uploaded');
        $I->see('My track');
    }

    public function canNotUploadInvalidTrackFile(AcceptanceTester $I)
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
        $I->attachFile('input[id="trackFile"]', 'invalid-sample.gpx');
        $I->click('Submit');
        $I->see('Invalid GPX file format');
    }
}
