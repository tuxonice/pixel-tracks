<?php

namespace Acceptance;

use Tests\Support\AcceptanceTester;

class TrackPageCest
{
    public function canSeeTrackDetail(AcceptanceTester $I)
    {
        $I->amOnPage('/send-magic-link');
        $I->see('Send me a Magic link');
        $I->fillField('email', 'user01@example.com');
        $I->click('Send');
        $I->see('Please verify your mailbox');
        $I->seeInDatabase('users', ['email' => 'user01@example.com']);
        $loginKey = $I->grabEntryFromDatabase('users', ['email' => 'user01@example.com']);
        $I->amOnPage('/login/' . $loginKey['login_key']);
        $I->see('Upload Track');

        $I->fillField('trackName', 'My track 1');
        $I->attachFile('input[id="trackFile"]', 'sample.gpx');
        $I->click('Submit');
        $I->see('New file uploaded');

        $I->seeNumberOfElements('tr', 2);

        // Get the 'Show on map' link
//        $mapLink = $I->grabAttributeFrom('a[title="Show on map"]', 'href');
//        $I->comment("Map Link: $mapLink");

        // Get the 'Show info' link
        $infoLink = $I->grabAttributeFrom('a[title="Show info"]', 'href');
        $I->amOnPage($infoLink);
        $I->see('Track Info');
        $I->see('My track 1');
        $I->see('4.74 Km');
    }

    public function canDeleteTrack(AcceptanceTester $I)
    {
        $I->amOnPage('/send-magic-link');
        $I->see('Send me a Magic link');
        $I->fillField('email', 'user02@example.com');
        $I->click('Send');
        $I->see('Please verify your mailbox');
        $I->seeInDatabase('users', ['email' => 'user02@example.com']);
        $loginKey = $I->grabEntryFromDatabase('users', ['email' => 'user02@example.com']);
        $I->amOnPage('/login/' . $loginKey['login_key']);
        $I->see('Upload Track');

        $I->fillField('trackName', 'My track 2');
        $I->attachFile('input[id="trackFile"]', 'sample.gpx');
        $I->click('Submit');
        $I->see('New file uploaded');

        $I->seeNumberOfElements('tr', 2);

        $infoLink = $I->grabAttributeFrom('a[title="Show info"]', 'href');
        $I->amOnPage($infoLink);
        $I->see('Track Info');
        $I->see('My track 2');
        $I->see('4.74 Km');

        $I->click('Delete');
        $I->see('Track deleted');
        $I->seeInCurrentUrl('/profile');
    }

    public function canSeeTrackMap(AcceptanceTester $I)
    {
        $I->amOnPage('/send-magic-link');
        $I->see('Send me a Magic link');
        $I->fillField('email', 'user03@example.com');
        $I->click('Send');
        $I->see('Please verify your mailbox');
        $I->seeInDatabase('users', ['email' => 'user03@example.com']);
        $loginKey = $I->grabEntryFromDatabase('users', ['email' => 'user03@example.com']);
        $I->amOnPage('/login/' . $loginKey['login_key']);
        $I->see('Upload Track');

        $I->fillField('trackName', 'My track 1');
        $I->attachFile('input[id="trackFile"]', 'sample.gpx');
        $I->click('Submit');
        $I->see('New file uploaded');

        $I->seeNumberOfElements('tr', 2);

        $mapLink = $I->grabAttributeFrom('a[title="Show on map"]', 'href');
        $I->amOnPage($mapLink);
        $I->see('My track 1');
        $I->see('4.74 Km');
        $I->see('Points: 105');
    }
}
