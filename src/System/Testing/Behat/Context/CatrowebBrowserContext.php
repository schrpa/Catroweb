<?php

namespace App\System\Testing\Behat\Context;

use App\DB\Entity\Project\Program;
use App\DB\Entity\User\Comment\UserComment;
use App\DB\Entity\User\Notifications\CatroNotification;
use App\DB\Entity\User\RecommenderSystem\UserLikeSimilarityRelation;
use App\DB\Entity\User\RecommenderSystem\UserRemixSimilarityRelation;
use App\Project\Apk\ApkRepository;
use App\Project\Apk\JenkinsDispatcher;
use App\Security\TokenGenerator;
use App\System\Testing\FixedTokenGenerator;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\ResponseTextException;
use Exception;
use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class CatrowebBrowserContext.
 *
 * Extends the basic browser utilities with Catroweb specific actions.
 */
class CatrowebBrowserContext extends BrowserContext
{
  final public const AVATAR_DIR = './tests/TestData/DataFixtures/AvatarImages/';

  final public const ALREADY_IN_DB_USER = 'AlreadyinDB';

  protected bool $use_real_oauth_javascript_code = false;

  protected ?Program $my_program = null;

  // -------------------------------------------------------------------------------------------------------------------
  //  Initialization
  // -------------------------------------------------------------------------------------------------------------------

  /**
   * Initializes context with parameters from behat.yaml.
   */
  public function __construct(KernelInterface $kernel)
  {
    parent::__construct($kernel);
    setlocale(LC_ALL, 'en');
  }

  public static function getAcceptedSnippetType(): string
  {
    return 'regex';
  }

  // -------------------------------------------------------------------------------------------------------------------
  //  Hook
  // -------------------------------------------------------------------------------------------------------------------

  /**
   * @BeforeScenario
   *
   * @throws Exception
   */
  public function initACL(): void
  {
    $application = new Application($this->getKernel());
    $application->setAutoExit(false);

    $input = new ArrayInput(['command' => 'sonata:admin:setup-acl']);
    $application->run($input, new NullOutput());
  }

  // --------------------------------------------------------------------------------------------------------------------
  //  Authentication
  // --------------------------------------------------------------------------------------------------------------------

  /**
   * @Given /^I( [^"]*)? log in as "([^"]*)" with the password "([^"]*)"$/
   * @Given /^I( [^"]*)? log in as "([^"]*)"$/
   *
   * @param mixed $try_to
   * @param mixed $username
   * @param mixed $password
   */
  public function iAmLoggedInAsWithThePassword($try_to, $username, $password = '123456'): void
  {
    $this->visit('/app/login');
    $this->iWaitForThePageToBeLoaded();
    $this->fillField('_username', $username);
    $this->fillField('_password', $password);
    $this->pressButton('Login');
    $this->iWaitForThePageToBeLoaded();
    if ('try to' === $try_to) {
      $this->assertPageNotContainsText('Your password or username was incorrect');
    }
    $this->getUserDataFixtures()->setCurrentUserByUsername($username);
  }

  /**
   * @When /^I logout$/
   */
  public function iLogout(): void
  {
    $this->assertElementOnPage('#btn-logout');
    $this->getSession()->getPage()->find('css', '#btn-logout')->click();
    $this->getUserDataFixtures()->setCurrentUser(null);
  }

  /**
   * @Then /^I should be logged (in|out)$/
   *
   * @param mixed $arg1
   */
  public function iShouldBeLogged($arg1): void
  {
    if ('in' === $arg1) {
      $this->assertPageNotContainsText('Your password or username was incorrect.');
      $this->getSession()->wait(2_000, 'window.location.href.search("login") == -1');
      $this->cookieShouldExist('BEARER');
    }
    if ('out' == $arg1) {
      $this->getSession()->wait(1_000, 'window.location.href.search("profile") == -1');
      $this->cookieShouldNotExist('BEARER');
    }
  }

  /**
   * @Given /^I use a (debug|release) build of the Catroid app$/
   *
   * @param mixed $build_type
   */
  public function iUseASpecificBuildTypeOfCatroidApp($build_type): void
  {
    $this->iUseTheUserAgentParameterized('0.998', 'PocketCode', '0.9.60', $build_type);
  }

  /**
   * @Given /^I use an ios app$/
   */
  public function iUseAnIOSApp(): void
  {
    // see org.catrobat.catroid.ui.WebViewActivity
    $platform = 'iPhone';
    $user_agent = ' Platform/'.$platform;
    $this->iUseTheUserAgent($user_agent);
  }

  // --------------------------------------------------------------------------------------------------------------------
  //  Everything -> ToDo CleanUp
  // --------------------------------------------------------------------------------------------------------------------

  /**
   * @Given /^I set the cookie "([^"]+)" to "([^"]*)"$/
   */
  public function iSetTheCookie(string $cookie_name, string $cookie_value): void
  {
    if ('NULL' === $cookie_value) {
      $cookie_value = null;
    }
    $this->getSession()->setCookie($cookie_name, $cookie_value);
  }

  /**
   * @Given /^cookie "([^"]+)" should exist"$/
   *
   * @throws ExpectationException
   */
  public function cookieShouldExist(string $cookie_name): void
  {
    $this->assertSession()->cookieExists($cookie_name);
  }

  /**
   * @Given /^cookie "([^"]+)" should not exist"$/
   *
   * @throws ExpectationException
   */
  public function cookieShouldNotExist(string $cookie_name): void
  {
    $cookie = $this->getSession()->getCookie($cookie_name);
    Assert::assertNull($cookie);
  }

  /**
   * @Given /^cookie "([^"]+)" with value "([^"]*)" should exist"$/
   *
   * @throws ExpectationException
   */
  public function cookieWithValueShouldExist(string $cookie_name, string $cookie_value): void
  {
    $this->cookieShouldExist($cookie_name);
    $cookie = $this->getSession()->getCookie($cookie_name);
    if (null !== $cookie) {
      $this->assertSession()->cookieEquals($cookie_name, $cookie_value);
    }
  }

  /**
   * @When /^I open the menu$/
   */
  public function iOpenTheMenu(): void
  {
    $sidebar_open = $this->getSession()->getPage()->find('css', '#sidebar')->isVisible();
    if (!$sidebar_open) {
      $this->getSession()->getPage()->find('css', '#top-app-bar__btn-sidebar-toggle')->click();
    }
    $this->iWaitForAjaxToFinish();
  }

  /**
   * @Then /^I should see (\d+) "([^"]*)"$/
   *
   * @param mixed $element_count
   * @param mixed $css_selector
   */
  public function iShouldSeeNumberOfElements($element_count, $css_selector): void
  {
    $elements = $this->getSession()->getPage()->findAll('css', $css_selector);
    $count = 0;
    foreach ($elements as $element) {
      if ($element->isVisible()) {
        ++$count;
      }
    }
    Assert::assertEquals($element_count, $count);
  }

  /**
   * @Then /^I should see a node with id "([^"]*)" having name "([^"]*)" and username "([^"]*)"$/
   *
   * @param mixed $node_id
   * @param mixed $expected_node_name
   * @param mixed $expected_username
   */
  public function iShouldSeeANodeWithNameAndUsername($node_id, $expected_node_name, $expected_username): void
  {
    /** @var array $result */
    $result = $this->getSession()->evaluateScript(
      "return { nodeName: RemixGraph.getInstance().getNodes().get('".$node_id."').name,
                     username: RemixGraph.getInstance().getNodes().get('".$node_id."').username };"
    );
    $actual_node_name = is_array($result['nodeName']) ? implode('', $result['nodeName']) : $result['nodeName'];
    $actual_username = $result['username'];
    Assert::assertEquals($expected_node_name, $actual_node_name);
    Assert::assertEquals($expected_username, $actual_username);
  }

  /**
   * @Then /^I should see an unavailable node with id "([^"]*)"$/
   *
   * @param mixed $node_id
   */
  public function iShouldSeeAnUnavailableNodeWithId($node_id): void
  {
    /** @var array $result */
    $result = $this->getSession()->evaluateScript(
      'return RemixGraph.getInstance().getNodes().get("'.$node_id.'");'
    );

    Assert::assertTrue(isset($result['id']));
    Assert::assertEquals($node_id, $result['id']);
    Assert::assertFalse(isset($result['name']));
    Assert::assertFalse(isset($result['username']));
  }

  /**
   * @Then /^I should see an edge from "([^"]*)" to "([^"]*)"$/
   *
   * @param mixed $from_id
   * @param mixed $to_id
   */
  public function iShouldSeeAnEdgeFromTo($from_id, $to_id): void
  {
    /** @var array $result */
    $result = $this->getSession()->evaluateScript(
      "return RemixGraph.getInstance().getEdges().get().filter(
        function (edge) { return edge.from === '".$from_id."' && edge.to === '".$to_id."'; }
      );"
    );
    Assert::assertCount(1, $result);
    Assert::assertEquals($from_id, $result[0]['from']);
    Assert::assertEquals($to_id, $result[0]['to']);
  }

  /**
   * @Then /^I should see the featured slider$/
   *
   * @throws ExpectationException
   */
  public function iShouldSeeTheFeaturedSlider(): void
  {
    $this->assertSession()->responseContains('featured');
    Assert::assertTrue($this->getSession()->getPage()->findById('feature-slider')->isVisible());
  }

  /**
   * @Then /^the selected language should be "([^"]*)"$/
   * @Given /^the selected language is "([^"]*)"$/
   *
   * @param mixed $arg1
   *
   * @throws ExpectationException
   */
  public function theSelectedLanguageShouldBe($arg1): void
  {
    switch ($arg1) {
      case 'English':
        $cookie = $this->getSession()->getCookie('hl');
        if (!empty($cookie)) {
          $this->assertSession()->cookieEquals('hl', 'en');
        }
        break;

      case 'Deutsch':
        $this->assertSession()->cookieEquals('hl', 'de_DE');
        break;

      case 'French':
        $this->assertSession()->cookieEquals('hl', 'fr_FR');
        break;

      default:
        Assert::assertTrue(false);
    }
  }

  /**
   * @Then /^I switch the language to "([^"]*)"$/
   *
   * @param mixed $arg1
   */
  public function iSwitchTheLanguageTo($arg1): void
  {
    switch ($arg1) {
      case 'English':
        $this->getSession()->setCookie('hl', 'en');
        break;
      case 'Deutsch':
        $this->getSession()->setCookie('hl', 'de_DE');
        break;
      case 'Russisch':
        $this->getSession()->setCookie('hl', 'ru_RU');
        break;
      case 'French':
        $this->getSession()->setCookie('hl', 'fr_FR');
        break;
      default:
        Assert::assertTrue(false);
    }
    $this->reload();
  }

  /**
   * @Then /^I click on the first "([^"]*)" button$/
   *
   * @param mixed $arg1
   *
   * @throws ElementNotFoundException
   */
  public function iClickOnTheFirstButton($arg1): void
  {
    $this->assertSession()->elementExists('css', $arg1);

    $this
      ->getSession()
      ->getPage()
      ->find('css', $arg1)
      ->click()
    ;
    $this->getSession()->wait(500);
  }

  /**
   * @Then /^I click on the column with the name "([^"]*)"$/
   *
   * @param mixed $arg1
   *
   * @throws Exception
   */
  public function iClickOnTheColumnName($arg1): void
  {
    $page = $this->getSession()->getPage();
    switch ($arg1) {
      case 'Name':
        $page
          ->find('xpath', '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/thead/tr/th[3]/a')
          ->click()
        ;
        break;
      case 'Views':
        $page
          ->find('xpath', '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/thead/tr/th[5]/a')
          ->click()
        ;
        break;
      case 'Downloads':
        $page
          ->find('xpath', '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/thead/tr/th[6]/a')
          ->click()
        ;
        break;
      case 'Upload Time':
      case 'Id':
        $page
          ->find('xpath', '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/thead/tr/th[1]/a')
          ->click()
        ;
        break;
      case 'Word':
        $page
          ->find('xpath', '//div[1]/div/section[2]/div[2]/div/form/div/div[1]/table/thead/tr/th[3]/a')
          ->click()
        ;
        break;
      case 'Clicked At':
          $page
            ->find('xpath', '//div/div/section[2]/div[2]/div/div/div[1]/table/thead/tr/th[9]/a')
            ->click()
          ;
          break;
      case \Locale::class:
          $page
            ->find('xpath', '//div/div/section[2]/div[2]/div/div/div[1]/table/thead/tr/th[10]/a')
            ->click()
          ;
          break;
      case 'Type':
           $page
             ->find('xpath', '//div/div/section[2]/div[2]/div/div/div[1]/table/thead/tr/th[2]/a')
             ->click()
           ;
           break;
      case 'Tag':
           $page
             ->find('xpath', '//div/div/section[2]/div[2]/div/div/div[1]/table/thead/tr/th[7]/a')
             ->click()
           ;
           break;
      case 'User Agent':
           $page
             ->find('xpath', '//div/div/section[2]/div[2]/div/div/div[1]/table/thead/tr/th[11]/a')
             ->click()
           ;
           break;
      case 'Referrer':
           $page
             ->find('xpath', '//div/div/section[2]/div[2]/div/div/div[1]/table/thead/tr/th[12]/a')
             ->click()
           ;
           break;

        default:
        throw new Exception('Wrong Option');
    }
  }

  /**
   * @Then /^I change the visibility of the project number "([^"]*)" in the list to "([^"]*)"$/
   *
   * @param mixed $program_number
   * @param mixed $visibility
   *
   * @throws Exception
   */
  public function iChangeTheVisibilityOfTheProgram($program_number, $visibility): void
  {
    // /param program number contains the number of the program position in the list on the admin page
    // /
    $page = $this->getSession()->getPage();

    // /click the visibility button (yes/no)
    $page
      ->find('xpath', '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/tbody/tr['.$program_number.']/td[10]/span')
      ->click()
    ;

    $this->iSelectTheOptionInThePopup($visibility);
  }

  /**
   * @Then /^I change the visibility of the project number "([^"]*)" in the approve list to "([^"]*)"$/
   *
   * @param string $program_number
   * @param string $visibility
   *
   * @throws Exception
   */
  public function iChangeTheVisibilityOfTheProgramInTheApproveList($program_number, $visibility): void
  {
    // /param program number contains the number of the program position in the list on the admin page
    // /
    $page = $this->getSession()->getPage();

    // /click the visibility button (yes/no)
    $page
      ->find('xpath', '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/tbody/tr['.$program_number.']/td[5]/span')
      ->click()
    ;

    $this->iSelectTheOptionInThePopup($visibility);
  }

  /**
   * @Then /^I change the approval of the project number "([^"]*)" in the list to "([^"]*)"$/
   *
   * @param mixed $program_number
   * @param mixed $approved
   *
   * @throws Exception
   */
  public function iChangeTheApprovalOfTheProject($program_number, $approved): void
  {
    // /param program number contains the number of the program position in the list on the admin page
    // /
    $page = $this->getSession()->getPage();
    // /click the visibility button (yes/no)
    $page
      ->find('xpath', '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/tbody/tr['.$program_number.']/td[9]/span')
      ->click()
    ;

    $this->iSelectTheOptionInThePopup($approved);
  }

  /**
   * @Then /^I change the approval of the project number "([^"]*)" in the approve list to "([^"]*)"$/
   *
   * @param string $program_number
   * @param string $approved
   *
   * @throws Exception
   */
  public function iChangeTheApprovalOfTheProjectInApproveList($program_number, $approved): void
  {
    // /param program number contains the number of the program position in the list on the admin page
    // /
    $page = $this->getSession()->getPage();
    // /click the visibility button (yes/no)
    $page
      ->find('xpath', '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/tbody/tr['.$program_number.']/td[6]/span')
      ->click()
    ;

    $this->iSelectTheOptionInThePopup($approved);
  }

  /**
   * @Then /^I change the flavor of the project number "([^"]*)" in the list to "([^"]*)"$/
   *
   * @param mixed $program_number
   * @param mixed $flavor
   *
   * @throws Exception
   */
  public function iChangeTheFlavorOfTheProject($program_number, $flavor): void
  {
    // /param program number contains the number of the program position in the list on the admin page

    $page = $this->getSession()->getPage();
    // /click the visibility button (yes/no)
    $page
      ->find('xpath', '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/tbody/tr['.$program_number.']/td[4]/span')
      ->click()
    ;
    // click the input on the popup to show yes or no option
    $page
      ->find('css', '.editable-input')
      ->click()
    ;

    switch ($flavor) {
      case 'pocketcode':
        $page
          ->find('css', 'select.form-control > option:nth-child(1)')
          ->click()
        ;
        break;
      case 'pocketalice':
        $page
          ->find('css', 'select.form-control > option:nth-child(2)')
          ->click()
        ;
        break;
      case 'pocketgalaxy':
        $page
          ->find('css', 'select.form-control > option:nth-child(3)')
          ->click()
        ;
        break;
      case 'phirocode':
        $page
          ->find('css', 'select.form-control > option:nth-child(4)')
          ->click()
        ;
        break;
      case 'luna':
        $page
          ->find('css', 'select.form-control > option:nth-child(5)')
          ->click()
        ;
        break;
      case 'create@school':
        $page
          ->find('css', 'select.form-control > option:nth-child(6)')
          ->click()
        ;
        break;
      case 'embroidery':
        $page
          ->find('css', 'select.form-control > option:nth-child(7)')
          ->click()
        ;
        break;
      case 'arduino':
        $page
          ->find('css', 'select.form-control > option:nth-child(8)')
          ->click()
        ;
        break;
      default:
        throw new Exception('Wrong flavor');
    }

    // click button to confirm the selection
    $page
      ->find('css', 'button.btn-sm:nth-child(1)')
      ->click()
    ;
  }

  /**
   * @Then /^I change upload of the entry number "([^"]*)" in the list to "([^"]*)"$/
   *
   * @param mixed $program_number
   * @param mixed $approved
   *
   * @throws Exception
   */
  public function iChangeUploadOfTheEntry($program_number, $approved): void
  {
    $page = $this->getSession()->getPage();
    $page
      ->find('xpath', '//div[1]/div/section[2]/div[2]/div/form/div/div/table/tbody/tr['.$program_number.']/td[4]/span')
      ->click()
    ;

    $this->iSelectTheOptionInThePopup($approved);
    $this->iWaitForAjaxToFinish();
  }

  /**
   * @Then /^I change report of the entry number "([^"]*)" in the list to "([^"]*)"$/
   *
   * @param mixed $program_number
   * @param mixed $approved
   *
   * @throws Exception
   */
  public function iChangeReportOfTheEntry($program_number, $approved): void
  {
    $page = $this->getSession()->getPage();
    $page
      ->find('xpath', '//div[1]/div/section[2]/div[2]/div/form/div/div/table/tbody/tr['.$program_number.']/td[5]/span')
      ->click()
    ;

    $this->iSelectTheOptionInThePopup($approved);
    $this->iWaitForAjaxToFinish();
  }

  /**
   * @Then /^I click action button "([^"]*)" of the entry number "([^"]*)"$/
   *
   * @param mixed $action_button
   * @param mixed $entry_number
   *
   * @throws Exception
   */
  public function iClickActionButtonOfEntry($action_button, $entry_number): void
  {
    $page = $this->getSession()->getPage();
    switch ($action_button) {
      case 'edit':
        $page
          ->find('xpath', '//div[1]/div/section[2]/div[2]/div/form/div/div/table/tbody/tr['.$entry_number.']/td[6]/div/a[1]')
          ->click()
        ;
        break;
      case 'delete':
        $page
          ->find('xpath', '//div[1]/div/section[2]/div[2]/div/form/div/div/table/tbody/tr['.$entry_number.']/td[6]/div/a[2]')
          ->click()
        ;
        break;
    }
  }

  /**
   * @Then /^I check the batch action box of entry "([^"]*)"$/
   *
   * @param mixed $entry_number
   *
   * @throws Exception
   */
  public function iCheckBatchActionBoxOfEntry($entry_number): void
  {
    $page = $this->getSession()->getPage();
    $page
      ->find('xpath', '//div[1]/div/section[2]/div[2]/div/form/div/div/table/tbody/tr['.$entry_number.']/td/div')
      ->click()
    ;
  }

  /**
   * @Then /^I click on the username "([^"]*)"$/
   *
   * @param mixed $username
   *
   * @throws ElementNotFoundException
   */
  public function iClickOnTheUsername($username): void
  {
    $this->assertSession()->elementExists('xpath', "//a[contains(text(),'".$username."')]");

    $page = $this->getSession()->getPage();
    $page
      ->find('xpath', "//a[contains(text(),'".$username."')]")
      ->click()
    ;
  }

  /**
   * @Then /^I click on the program name "([^"]*)"$/
   *
   * @param mixed $program_name
   *
   * @throws ElementNotFoundException
   */
  public function iClickOnTheProgramName($program_name): void
  {
    $this->assertSession()->elementExists('xpath', "//a[contains(text(),'".$program_name."')]");

    $page = $this->getSession()->getPage();
    $page
      ->find('xpath', "//a[contains(text(),'".$program_name."')]")
      ->click()
    ;
  }

  /**
   * @Then /^I click on the show button of the program number "([^"]*)" in the list$/
   *
   * @param mixed $program_number
   *
   * @throws ElementNotFoundException
   */
  public function iClickOnTheShowButton($program_number): void
  {
    $page = $this->getSession()->getPage();
    $this->assertSession()->elementExists('xpath',
      '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/tbody/tr['.$program_number.']/td[11]/div/a');

    $page
      ->find('xpath', '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/tbody/tr['.$program_number.']/td[11]/div/a')
      ->click()
    ;
  }

  /**
   * @Then /^I click on the show button of program with id "([^"]*)" in the approve list$/
   *
   * @param string $program_id
   */
  public function iClickOnTheShowButtonInTheApproveList($program_id): void
  {
    $page = $this->getSession()->getPage();
    $page->find('xpath', "//a[contains(@href,'/admin/approve/".$program_id."/show')]")->click();
  }

  /**
   * @Then /^I click on the code view button$/
   */
  public function iClickOnTheCodeViewButtonInTheApproveList(): void
  {
    $page = $this->getSession()->getPage();
    $page->findById('code-view')->click();
  }

  /**
   * @Then /^I click on the edit button of the extension number "([^"]*)" in the extensions list$/
   *
   * @param mixed $program_number
   *
   * @throws ElementNotFoundException
   */
  public function iClickOnTheEditButtonInAllExtensions($program_number): void
  {
    $page = $this->getSession()->getPage();
    $this->assertSession()->elementExists('xpath',
      '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/tbody/tr['.$program_number.']/td[4]/div/a');

    $page
      ->find('xpath', '//div[1]/div/section[2]/div[2]/div/div/div[1]/table/tbody/tr['.$program_number.']/td[4]/div/a')
      ->click()
    ;
  }

  /**
   * @Then /^I click on the add new button$/
   *
   * @throws ElementNotFoundException
   */
  public function iClickOnTheAddNewButton(): void
  {
    $page = $this->getSession()->getPage();
    $this->assertSession()->elementExists('xpath',
      "//a[contains(text(),'Add new')]");

    $page
      ->find('xpath', "//a[contains(text(),'Add new')]")
      ->click()
    ;
  }

  /**
   * @When /^I report program (\d+) with category "([^"]*)" and note "([^"]*)" in Browser$/
   *
   * @param mixed $program_id
   * @param mixed $category
   * @param mixed $note
   *
   * @throws ElementNotFoundException
   */
  public function iReportProgramWithNoteInBrowser($program_id, $category, $note): void
  {
    $this->visit('app/project/'.$program_id);
    $this->iWaitForThePageToBeLoaded();
    $this->iClick('#top-app-bar__btn-report-project');
    $this->iWaitForAjaxToFinish();
    $this->fillField('report-reason', $note);
    switch ($category) {
      case 'copyright':
        $this->iClickTheRadiobutton('#report-copyright');
        break;
      case 'inappropriate':
        $this->iClickTheRadiobutton('#report-inappropriate');
        break;
      case 'spam':
        $this->iClickTheRadiobutton('#report-spam');
        break;
      case 'dislike':
        $this->iClickTheRadiobutton('#report-dislike');
        break;
    }

    $this->iClick('.swal2-confirm');
    $this->iWaitForAjaxToFinish();
    $this->assertPageContainsText('Your report was successfully sent!');
  }

  /**
   * @Given /^I write "([^"]*)" in textbox$/
   *
   * @param mixed $arg1
   */
  public function iWriteInTextbox($arg1): void
  {
    $textarea = $this->getSession()->getPage()->find('css', '#comment-message');
    Assert::assertNotNull($textarea, 'Textarea not found');
    $textarea->setValue($arg1);
  }

  /**
   * @Given /^I write "([^"]*)" in textarea$/
   *
   * @param mixed $arg1
   */
  public function iWriteInTextarea($arg1): void
  {
    $textarea = $this->getSession()->getPage()->find('css', '#edit-text');
    Assert::assertNotNull($textarea, 'Textarea not found');
    $textarea->setValue($arg1);
  }

  /**
   * @Then /^I click the "([^"]*)" RadioButton$/
   *
   * @param mixed $arg1
   */
  public function iClickTheRadiobutton($arg1): void
  {
    $page = $this->getSession()->getPage();
    $radioButton = $page->find('css', $arg1);
    $radioButton->click();
  }

  /**
   * @Then /^comments or catro notifications should not exist$/
   */
  public function commentsOrCatroNotificationsShouldNotExist(): void
  {
    $em = $this->getManager();
    $comments = $em->getRepository(UserComment::class)->findAll();
    $notifications = $em->getRepository(CatroNotification::class)->findAll();
    Assert::assertTrue(!$comments && !$notifications);
  }

  /**
   * @When /^(?:|I )attach the avatar "(?P<path>[^"]*)" to "(?P<field>(?:[^"]|\\")*)"$/
   *
   * @param mixed $field
   * @param mixed $path
   *
   * @throws ElementNotFoundException
   */
  public function attachFileToField($field, $path): void
  {
    $field = $this->fixStepArgument($field);
    $this->getSession()->getPage()->attachFileToField($field, realpath(self::AVATAR_DIR.$path));
  }

  /**
   * @Then /^the avatar img tag should( [^"]*)? have the "([^"]*)" data url$/
   *
   * @param mixed $not
   * @param mixed $name
   */
  public function theAvatarImgTagShouldHaveTheDataUrl($not, $name): void
  {
    $name = trim($name);
    $not = trim($not);

    $pre_source = $this->getSession()->getPage()->find('css', '.profile__basic-info__avatar__img');
    $source = '';
    if (!is_null($pre_source)) {
      $source = $pre_source->getAttribute('src') ?? '';
    } else {
      Assert::assertTrue(false, "Couldn't find avatar in .profile__basic-info__avatar__img");
    }
    $source = trim($source, '"');

    switch ($name) {
      case 'logo.png':
        $logoUrl = 'data:image/png;base64,'.base64_encode(file_get_contents(self::AVATAR_DIR.'logo.png'));
        $isSame = ($source === $logoUrl);
        'not' === $not ? Assert::assertFalse($isSame) : Assert::assertTrue($isSame);
        break;

      case 'fail.tif':
        $failUrl = 'data:image/tiff;base64,'.base64_encode(file_get_contents(self::AVATAR_DIR.'fail.tif'));
        $isSame = ($source === $failUrl);
        'not' === $not ? Assert::assertFalse($isSame) : Assert::assertTrue($isSame);
        break;

      case 'default':
        $defaultSource = 'images/default/avatar_default.png';
        'not' === $not ? Assert::assertStringNotContainsString($defaultSource, $source) : Assert::assertStringContainsString($defaultSource, $source);
        break;

      default:
        Assert::assertTrue(false);
    }
  }

  /**
   * @When /^I press enter in the search bar$/
   */
  public function iPressEnterInTheSearchBar(): void
  {
    // Hacky solution since triggering the submit event is not working
    $arg1 = trim('#top-app-bar__search-form__submit');
    $this->assertSession()->elementExists('css', $arg1);
    $this->getSession()->getPage()->find('css', $arg1)->click();
  }

  /**
   * @Then /^I should see media file with id "([^"]*)"$/
   *
   * @param mixed $id
   */
  public function iShouldSeeMediaFileWithId($id): void
  {
    $link = $this->getSession()->getPage()->find('css', '#mediafile-'.$id);
    Assert::assertNotNull($link);
  }

  /**
   * @Then /^I should not see media file with id "([^"]*)"$/
   *
   * @param mixed $id
   */
  public function iShouldNotSeeMediaFileWithId($id): void
  {
    $link = $this->getSession()->getPage()->find('css', '#mediafile-'.$id);
    Assert::assertNull($link);
  }

  /**
   * @Then /^I should see media file with id ([0-9]+) in category "([^"]*)"$/
   *
   * @param mixed $id
   * @param mixed $category
   */
  public function iShouldSeeMediaFileWithIdInCategory($id, $category): void
  {
    $link = $this->getSession()->getPage()
      ->find('css', '[data-name="'.$category.'"]')
      ->find('css', '#mediafile-'.$id)
    ;
    Assert::assertNotNull($link);
  }

  /**
   * @Then /^I should see ([0-9]+) media files? in category "([^"]*)"$/
   *
   * @param mixed $count
   * @param mixed $category
   */
  public function iShouldSeeNumberOfMediaFilesInCategory($count, $category): void
  {
    $elements = $this->getSession()->getPage()
      ->find('css', '[data-name="'.$category.'"]')
      ->findAll('css', '.mediafile')
    ;
    Assert::assertEquals($count, count($elements));
  }

  /**
   * @When /^I should see the video available at "([^"]*)"$/
   *
   * @param mixed $url
   */
  public function iShouldSeeTheVideoAvailableAt($url): void
  {
    $page = $this->getSession()->getPage();
    $video = $page->find('css', '.video-container > iframe');
    Assert::assertNotNull($video, 'Video not found!');
    Assert::assertTrue(str_contains((string) $video->getAttribute('src'), (string) $url));
  }

  /**
   * @Then /^I should see the slider with the values "([^"]*)"$/
   *
   * @param mixed $values
   */
  public function iShouldSeeTheSliderWithTheValues($values): void
  {
    $slider_items = explode(',', (string) $values);
    $owl_items = $this->getSession()->getPage()->findAll('css', '.carousel-item');
    $owl_items_count = count($owl_items);
    Assert::assertEquals($owl_items_count, count($slider_items));

    for ($index = 0; $index < $owl_items_count; ++$index) {
      $url = $slider_items[$index];
      if (!str_starts_with($url, 'http://')) {
        $program = $this->getProgramManager()->findOneByName($url);
        Assert::assertNotNull($program);
        Assert::assertNotNull($program->getId());
        $url = $this->getRouter()->generate('program', ['id' => $program->getId(), 'theme' => 'pocketcode']);
      }

      $feature_url = $owl_items[$index]->getAttribute('href');
      Assert::assertStringContainsString($url, $feature_url);
    }
  }

  /**
   * @When /^I press on the tag "([^"]*)"$/
   *
   * @param mixed $arg1
   *
   * @throws ElementNotFoundException
   */
  public function iPressOnTheTag($arg1): void
  {
    $xpath = '//*[@id="tags"]/div/a[normalize-space()="'.$arg1.'"]';
    $this->assertSession()->elementExists('xpath', $xpath);

    $this
      ->getSession()
      ->getPage()
      ->find('xpath', $xpath)
      ->click()
    ;
  }

  /**
   * @When /^I press on the extension "([^"]*)"$/
   *
   * @param mixed $name
   *
   * @throws ElementNotFoundException
   */
  public function iPressOnTheExtension($name): void
  {
    $xpath = '//*[@id="extensions"]/div/a[normalize-space()="'.$name.'"]';
    $this->assertSession()->elementExists('xpath', $xpath);

    $this
      ->getSession()
      ->getPage()
      ->find('xpath', $xpath)
      ->click()
    ;
  }

  /**
   * @Then /^I click the currently visible search icon$/
   */
  public function iClickTheCurrentlyVisibleSearchIcon(): void
  {
    $icon = $this->getSession()->getPage()->findById('top-app-bar__btn-search');
    if ($icon->isVisible()) {
      $icon->click();

      return;
    }
    Assert::assertTrue(false, 'Tried to click #top-app-bar__btn-search but no visible element was found.');
  }

  /**
   * @Given I use a valid JWT token for :username
   *
   * @param mixed $username
   */
  public function iUseAValidJwtTokenFor($username): void
  {
    $user = $this->getUserManager()->findUserByUsername($username);
    $token = $this->getJwtManager()->create($user);
    $this->getSession()->setRequestHeader('Authorization', 'Bearer '.$token);
  }

  /**
   * @Given I use a valid BEARER cookie for :username
   *
   * @param mixed $username
   */
  public function iUseAValidBEARERCookieFor($username): void
  {
    $user = $this->getUserManager()->findUserByUsername($username);
    $token = $this->getJwtManager()->create($user);
    $this->getSession()->setCookie('BEARER', $token);
  }

  /**
   * @Given I use an invalid JWT authorization header for :username
   */
  public function iUseAnInvalidJwtTokenFor(): void
  {
    $token = 'invalidToken';
    $this->getSession()->setRequestHeader('Authorization', 'Bearer '.$token);
  }

  /**
   * @Given I use an empty JWT token for :username
   */
  public function iUseAnEmptyJwtTokenFor(): void
  {
    $this->getSession()->setRequestHeader('Authorization', '');
  }

  /**
   * @Given I have a project zip :project_zip_name
   *
   * @param mixed $project_zip_name
   */
  public function iHaveAProject($project_zip_name): void
  {
    $filesystem = new Filesystem();
    $original_file = $this->FIXTURES_DIR.$project_zip_name;
    $target_file = sys_get_temp_dir().'/program_generated.catrobat';
    $filesystem->copy($original_file, $target_file, true);
  }

  /**
   * @Given I have a program
   */
  public function iHaveAProgram(): void
  {
    $this->generateProgramFileWith([]);
  }

  /**
   * @Given /^I am using pocketcode with language version "([^"]*)"$/
   *
   * @param mixed $version
   */
  public function iAmUsingPocketcodeWithLanguageVersion($version): void
  {
    $this->generateProgramFileWith([
      'catrobatLanguageVersion' => $version,
    ]);
  }

  /**
   * @Given I have an embroidery project
   */
  public function iHaveAnEmbroideryProject(): void
  {
    $this->generateProgramFileWith([], true);
  }

  /**
   * @Given /^I am using pocketcode for "([^"]*)" with version "([^"]*)"$/
   *
   * @param mixed $platform
   * @param mixed $version
   */
  public function iAmUsingPocketcodeForWithVersion($platform, $version): void
  {
    $this->generateProgramFileWith([
      'platform' => $platform,
      'applicationVersion' => $version,
    ]);
  }

  /**
   * @Given /^the token to upload an apk file is "([^"]*)"$/
   */
  public function theTokenToUploadAnApkFileIs(): void
  {
    // Defined in config_test.yaml
  }

  /**
   * @Given /^the jenkins job id is "([^"]*)"$/
   */
  public function theJenkinsJobIdIs(): void
  {
    // Defined in config_test.yaml
  }

  /**
   * @Given /^the jenkins token is "([^"]*)"$/
   */
  public function theJenkinsTokenIs(): void
  {
    // Defined in config_test.yaml
  }

  /**
   * @Then /^following parameters are sent to jenkins:$/
   */
  public function followingParametersAreSentToJenkins(TableNode $table): void
  {
    $parameter_defs = $table->getHash();
    $expected_parameters = [];
    foreach ($parameter_defs as $parameter_def) {
      $expected_parameters[$parameter_def['parameter']] = $parameter_def['value'];
    }
    $dispatcher = $this->getSymfonyService(JenkinsDispatcher::class);
    $parameters = $dispatcher->getLastParameters();

    foreach ($expected_parameters as $i => $expected_parameter) {
      Assert::assertMatchesRegularExpression(
        $expected_parameter,
        $parameters[$i]
      );
    }
  }

  /**
   * @Then /^the program apk status will.* be flagged "([^"]*)"$/
   *
   * @param mixed $arg1
   */
  public function theProgramApkStatusWillBeFlagged($arg1): void
  {
    $pm = $this->getProgramManager();
    $program = $pm->find('1');
    switch ($arg1) {
      case 'pending':
        Assert::assertEquals(Program::APK_PENDING, $program->getApkStatus());
        break;
      case 'ready':
        Assert::assertEquals(Program::APK_READY, $program->getApkStatus());
        break;
      case 'none':
        Assert::assertEquals(Program::APK_NONE, $program->getApkStatus());
        break;
      default:
        throw new PendingException('Unknown state: '.$arg1);
    }
  }

  /**
   * @Given /^I requested jenkins to build it$/
   */
  public function iRequestedJenkinsToBuildIt(): void
  {
  }

  /**
   * @Then /^it will be stored on the server$/
   */
  public function itWillBeStoredOnTheServer(): void
  {
    $directory = $this->getSymfonyParameterAsString('catrobat.apk.dir');
    $finder = new Finder();
    $finder->in($directory)->depth(0);
    Assert::assertEquals(1, $finder->count());
  }

  /**
   * @Given /^the program apk status is flagged "([^"]*)"$/
   *
   * @param mixed $arg1
   */
  public function theProgramApkStatusIsFlagged($arg1): void
  {
    $pm = $this->getProgramManager();
    $program = $pm->find('1');
    switch ($arg1) {
      case 'pending':
        $program->setApkStatus(Program::APK_PENDING);
        break;
      case 'ready':
        $program->setApkStatus(Program::APK_READY);
        /* @var $apk_repository ApkRepository */
        $apk_repository = $this->getSymfonyService(ApkRepository::class);
        $apk_repository->save(new File(strval($this->getTempCopy($this->FIXTURES_DIR.'/test.catrobat'))), $program->getId());
        break;
      default:
        $program->setApkStatus(Program::APK_NONE);
    }
    $pm->save($program);
  }

  /**
   * @Then /^no build request will be sent to jenkins$/
   */
  public function noBuildRequestWillBeSentToJenkins(): void
  {
    $dispatcher = $this->getSymfonyService(JenkinsDispatcher::class);
    $parameters = $dispatcher->getLastParameters();
    Assert::assertNull($parameters);
  }

  /**
   * @Then /^the apk file will be deleted$/
   */
  public function theApkFileWillBeDeleted(): void
  {
    $directory = $this->getSymfonyParameter('catrobat.apk.dir');
    $finder = new Finder();
    $finder->in($directory)->depth(0);
    Assert::assertEquals(0, $finder->count());
  }

  /**
   * @Then /^I should see the reported table:$/
   *
   * @throws ResponseTextException
   */
  public function shouldSeeReportedTable(TableNode $table): void
  {
    $user_stats = $table->getHash();
    foreach ($user_stats as $user_stat) {
      $this->assertSession()->pageTextContains($user_stat['#Reported Comments']);
      $this->assertSession()->pageTextContains($user_stat['#Reported Programs']);
      $this->assertSession()->pageTextContains($user_stat['Username']);
      $this->assertSession()->pageTextContains($user_stat['Email']);
    }
  }

  /**
   * @Then /^I should see the notifications table:$/
   *
   * @throws ResponseTextException
   */
  public function shouldSeeNotificationTable(TableNode $table): void
  {
    $user_stats = $table->getHash();
    foreach ($user_stats as $user_stat) {
      $this->assertSession()->pageTextContains($user_stat['User']);
      $this->assertSession()->pageTextContains($user_stat['User Email']);
      $this->assertSession()->pageTextContains($user_stat['Upload']);
      $this->assertSession()->pageTextContains($user_stat['Report']);
    }
  }

  /**
   * @Then /^I should see the table with all projects in the following order:$/
   */
  public function shouldSeeFollowingTable(TableNode $table): void
  {
    $user_stats = $table->getHash();
    $td = $this->getSession()->getPage()->findAll('css', '.table tbody tr');

    $actual_values = [];
    foreach ($td as $value) {
      $actual_values[] = $value->getText();
    }

    Assert::assertEquals(count($actual_values), count($user_stats), 'Wrong number of projects in table');

    $counter = 0;
    foreach ($user_stats as $user_stat) {
      $user_stat = array_filter($user_stat);
      Assert::assertEquals(implode(' ', $user_stat), $actual_values[$counter]);
      ++$counter;
    }
  }

  /**
   * @Then /^I should see the example table:$/
   *
   * @throws ResponseTextException
   */
  public function shouldSeeExampleTable(TableNode $table): void
  {
    $user_stats = $table->getHash();
    foreach ($user_stats as $user_stat) {
      $this->assertSession()->pageTextContains($user_stat['Id']);
      $this->assertSession()->pageTextContains($user_stat['Program']);
      $this->assertSession()->pageTextContains($user_stat['Flavor']);
      $this->assertSession()->pageTextContains($user_stat['Priority']);
    }
  }

  /**
   * @Then /^I should see the following not approved projects:$/
   */
  public function seeNotApprovedProjects(TableNode $table): void
  {
    $user_stats = $table->getHash();
    $td = $this->getSession()->getPage()->findAll('css', '.table tbody tr');

    $actual_values = [];
    foreach ($td as $value) {
      $actual_values[] = $value->getText();
    }

    Assert::assertEquals(count($actual_values), count($user_stats), 'Wrong number of projects in table');

    $counter = 0;
    foreach ($user_stats as $user_stat) {
      Assert::assertEquals(implode(' ', $user_stat), $actual_values[$counter]);
      ++$counter;
    }
  }

  /**
   * @Then /^I should see the reported programs table:$/
   *
   * @throws ResponseTextException
   */
  public function seeReportedProgramsTable(TableNode $table): void
  {
    $user_stats = $table->getHash();
    foreach ($user_stats as $user_stat) {
      $this->assertSession()->pageTextContains($user_stat['Note']);
      $this->assertSession()->pageTextContains($user_stat['State']);
      $this->assertSession()->pageTextContains($user_stat['Category']);
      $this->assertSession()->pageTextContains($user_stat['Reporting User']);
      $this->assertSession()->pageTextContains($user_stat['Program']);
      $this->assertSession()->pageTextContains($user_stat['Program Visible']);
    }
  }

  /**
   * @Then /^I should see the ready apks table:$/
   *
   * @throws ResponseTextException
   */
  public function seeReadyApksTable(TableNode $table): void
  {
    $user_stats = $table->getHash();
    foreach ($user_stats as $user_stat) {
      $this->assertSession()->pageTextContains($user_stat['Id']);
      $this->assertSession()->pageTextContains($user_stat['User']);
      $this->assertSession()->pageTextContains($user_stat['Name']);
      $this->assertSession()->pageTextContains($user_stat['Apk Request Time']);
    }
  }

  /**
   * @Then /^I should see the pending apk table:$/
   *
   * @throws ResponseTextException
   */
  public function seePendingApkTable(TableNode $table): void
  {
    $user_stats = $table->getHash();
    foreach ($user_stats as $user_stat) {
      $this->assertSession()->pageTextContains($user_stat['Id']);
      $this->assertSession()->pageTextContains($user_stat['User']);
      $this->assertSession()->pageTextContains($user_stat['Name']);
      $this->assertSession()->pageTextContains($user_stat['Apk Request Time']);
      $this->assertSession()->pageTextContains($user_stat['Apk Status']);
    }
  }

  /**
   * @Then /^I should see the survey table:$/
   *
   * @throws ResponseTextException
   */
  public function seeSurveyTable(TableNode $table): void
  {
    $survey_stats = $table->getHash();
    foreach ($survey_stats as $survey_stat) {
      $this->assertSession()->pageTextContains($survey_stat['Language Code']);
      $this->assertSession()->pageTextContains($survey_stat['Url']);
      $this->assertSession()->pageTextContains($survey_stat['Active']);
    }
  }

  /**
   * @Then /^I should see the achievements table:$/
   *
   * @throws ResponseTextException
   */
  public function seeAchievementTable(TableNode $table): void
  {
    $this->assertSession()->pageTextContains('Priority');
    $this->assertSession()->pageTextContains('Internal Title');
    $this->assertSession()->pageTextContains('Internal Description');
    $this->assertSession()->pageTextContains('Color');
    $this->assertSession()->pageTextContains('Enabled');
    $this->assertSession()->pageTextContains('Unlocked by');

    $data = $table->getHash();
    foreach ($data as $entry) {
      $this->assertSession()->pageTextContains($entry['Priority']);
      $this->assertSession()->pageTextContains($entry['Internal Title']);
      $this->assertSession()->pageTextContains($entry['Internal Description']);
      $this->assertSession()->pageTextContains($entry['Color']);
      $this->assertSession()->pageTextContains($entry['Enabled']);
      $this->assertSession()->pageTextContains($entry['Unlocked by']);
    }
  }

  /**
   * @Then /^I should see the tags table:$/
   *
   * @throws ResponseTextException
   */
  public function seeTagsTable(TableNode $table): void
  {
    $this->assertSession()->pageTextContains('Internal Title');
    $this->assertSession()->pageTextContains('Enabled');
    $this->assertSession()->pageTextContains('Projects with tag');

    $data = $table->getHash();
    foreach ($data as $entry) {
      $this->assertSession()->pageTextContains($entry['Internal Title']);
      $this->assertSession()->pageTextContains($entry['Enabled']);
      $this->assertSession()->pageTextContains($entry['Projects with tag']);
    }
  }

  /**
   * @Then /^I should see the extensions table:$/
   *
   * @throws ResponseTextException
   */
  public function seeExtensionsTable(TableNode $table): void
  {
    $this->assertSession()->pageTextContains('Internal Title');
    $this->assertSession()->pageTextContains('Enabled');
    $this->assertSession()->pageTextContains('Projects with extension');

    $data = $table->getHash();
    foreach ($data as $entry) {
      $this->assertSession()->pageTextContains($entry['Internal Title']);
      $this->assertSession()->pageTextContains($entry['Enabled']);
      $this->assertSession()->pageTextContains($entry['Projects with extension']);
    }
  }

  /**
   * @Then /^I should see the cron jobs table:$/
   *
   * @throws ResponseTextException
   */
  public function seeCronJobTable(TableNode $table): void
  {
    $this->assertSession()->pageTextContains('Name');
    $this->assertSession()->pageTextContains('State');
    $this->assertSession()->pageTextContains('Cron Interval');
    $this->assertSession()->pageTextContains('Start At');
    $this->assertSession()->pageTextContains('End At');
    $this->assertSession()->pageTextContains('Result Code');

    $survey_stats = $table->getHash();
    foreach ($survey_stats as $survey_stat) {
      $this->assertSession()->pageTextContains($survey_stat['Name']);
      $this->assertSession()->pageTextContains($survey_stat['State']);
      $this->assertSession()->pageTextContains($survey_stat['Cron Interval']);
      $this->assertSession()->pageTextContains($survey_stat['Start At']);
      $this->assertSession()->pageTextContains($survey_stat['End At']);
      $this->assertSession()->pageTextContains($survey_stat['Result Code']);
    }
  }

  /**
   * @Then /^I should see the media package categories table:$/
   *
   * @throws ResponseTextException
   */
  public function seeMediaPackageCategoriesTable(TableNode $table): void
  {
    $user_stats = $table->getHash();
    foreach ($user_stats as $user_stat) {
      $this->assertSession()->pageTextContains($user_stat['Id']);
      $this->assertSession()->pageTextContains($user_stat['Name']);
      $this->assertSession()->pageTextContains($user_stat['Package']);
      $this->assertSession()->pageTextContains($user_stat['Priority']);
    }
  }

  /**
   * @Then /^I should see the media packages table:$/
   *
   * @throws ResponseTextException
   */
  public function seeMediaPackages(TableNode $table): void
  {
    $user_stats = $table->getHash();
    foreach ($user_stats as $user_stat) {
      $this->assertSession()->pageTextContains($user_stat['name']);
      $this->assertSession()->pageTextContains($user_stat['name_url']);
    }
  }

  /**
   * @Then /^I should see the media package files table:$/
   *
   * @throws ResponseTextException
   */
  public function seeMediaPackageFilesTable(TableNode $table): void
  {
    $user_stats = $table->getHash();
    foreach ($user_stats as $user_stat) {
      $this->assertSession()->pageTextContains($user_stat['Id']);
      $this->assertSession()->pageTextContains($user_stat['Name']);
      $this->assertSession()->pageTextContains($user_stat['Category']);
      $this->assertSession()->pageTextContains($user_stat['Author']);
      $this->assertSession()->pageTextContains($user_stat['Flavors']);
      $this->assertSession()->pageTextContains($user_stat['Downloads']);
      $this->assertSession()->pageTextContains($user_stat['Active']);
    }
  }

  /**
   * @Given /^there is a file "([^"]*)" with size "([^"]*)" bytes in the APK-folder$/
   *
   * @param mixed $filename
   * @param mixed $size
   */
  public function thereIsAFileWithSizeBytesInTheApkFolder($filename, $size): void
  {
    $this->generateFileInPath($this->getSymfonyParameter('catrobat.apk.dir'), $filename, $size);
  }

  /**
   * @Then /^program with id "([^"]*)" should have no apk$/
   *
   * @param mixed $program_id
   */
  public function programWithIdShouldHaveNoApk($program_id): void
  {
    $program_manager = $this->getProgramManager();
    $program = $program_manager->find($program_id);
    Assert::assertEquals(Program::APK_NONE, $program->getApkStatus());
  }

  /**
   * @Given /^there is a file "([^"]*)" with size "([^"]*)" bytes in the compressed-folder$/
   *
   * @param mixed $filename
   * @param mixed $size
   */
  public function thereIsAFileWithSizeBytesInTheExtractedFolder($filename, $size): void
  {
    $this->generateFileInPath($this->getSymfonyParameter('catrobat.file.storage.dir'),
      $filename, $size);
  }

  /**
   * @Then the resources should not contain the unnecessary files
   */
  public function theResourcesShouldNotContainTheUnnecessaryFiles(): void
  {
    $files = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($this->EXTRACT_RESOURCES_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
      $filename = $file->getFilename();
      Assert::assertStringNotContainsString('remove_me', $filename);
    }
  }

  /**
   * @Given /^I am a valid user$/
   */
  public function iAmAValidUser(): void
  {
    $this->insertUser([
      'name' => 'BehatGeneratedName',
      'token' => 'BehatGeneratedToken',
      'password' => 'BehatGeneratedPassword',
    ]);
  }

  /**
   * @Then /^I should get following like similarities:$/
   */
  public function iShouldGetFollowingLikePrograms(TableNode $table): void
  {
    $all_like_similarities = $this->getUserLikeSimilarityRelationRepository()->findAll();
    $all_like_similarities_count = count($all_like_similarities);
    $expected_like_similarities = $table->getHash();
    Assert::assertEquals(count($expected_like_similarities), $all_like_similarities_count,
      'Wrong number of returned similarity entries');
    for ($i = 0; $i < $all_like_similarities_count; ++$i) {
      /** @var UserLikeSimilarityRelation $like_similarity */
      $like_similarity = $all_like_similarities[$i];
      Assert::assertEquals(
        $expected_like_similarities[$i]['first_user_id'],
        $like_similarity->getFirstUserId(),
        'Wrong value for first_user_id or wrong order of results'
      );
      Assert::assertEquals(
        $expected_like_similarities[$i]['second_user_id'],
        $like_similarity->getSecondUserId(),
        'Wrong value for second_user_id'
      );
      Assert::assertEquals(
        round($expected_like_similarities[$i]['similarity'], 3),
        round($like_similarity->getSimilarity(), 3),
        'Wrong value for similarity'
      );
    }
  }

  /**
   * @Then /^I should get following remix similarities:$/
   */
  public function iShouldGetFollowingRemixPrograms(TableNode $table): void
  {
    $all_remix_similarities = $this->getUserRemixSimilarityRelationRepository()->findAll();
    $all_remix_similarities_count = count($all_remix_similarities);
    $expected_remix_similarities = $table->getHash();
    Assert::assertEquals(count($expected_remix_similarities), $all_remix_similarities_count,
      'Wrong number of returned similarity entries');
    for ($i = 0; $i < $all_remix_similarities_count; ++$i) {
      /** @var UserRemixSimilarityRelation $remix_similarity */
      $remix_similarity = $all_remix_similarities[$i];
      Assert::assertEquals(
        $expected_remix_similarities[$i]['first_user_id'], $remix_similarity->getFirstUserId(),
        'Wrong value for first_user_id or wrong order of results'
      );
      Assert::assertEquals(
        $expected_remix_similarities[$i]['second_user_id'], $remix_similarity->getSecondUserId(),
        'Wrong value for second_user_id'
      );
      Assert::assertEquals(round($expected_remix_similarities[$i]['similarity'], 3),
        round($remix_similarity->getSimilarity(), 3),
        'Wrong value for similarity');
    }
  }

  /**
   * @Given the next generated token will be :token
   *
   * @param mixed $token
   */
  public function theNextGeneratedTokenWillBe($token): void
  {
    $token_generator = $this->getSymfonyService(TokenGenerator::class);
    $token_generator->setTokenGenerator(new FixedTokenGenerator($token));
  }

  /**
   * @Given /^I have a program with arduino, mindstorms and phiro extensions$/
   */
  public function iHaveAProgramWithArduinoMindstormsAndPhiroExtensions(): void
  {
    $filesystem = new Filesystem();
    $original_file = $this->FIXTURES_DIR.'extensions.catrobat';
    $target_file = sys_get_temp_dir().'/program_generated.catrobat';
    $filesystem->copy($original_file, $target_file, true);
  }

  /**
   * @Then /^We can\'t test anything here$/
   *
   * @throws Exception
   */
  public function weCantTestAnythingHere(): never
  {
    throw new Exception(':(');
  }

  /**
   * @Then the button :button should be disabled until download is finished
   */
  public function theButtonShouldBeDisabledUntilDownloadIsFinished(string $button): void
  {
    $this->theElementShouldBeVisible($button);
  }

  /**
   * @Then /^I should see the featured table:$/
   */
  public function iShouldSeeTheFeaturedTable(TableNode $table): void
  {
    $user_stats = $table->getHash();
    foreach ($user_stats as $user_stat) {
      $this->assertSession()->pageTextContains($user_stat['Id']);
      $this->assertSession()->pageTextContains($user_stat['Program']);
      $this->assertSession()->pageTextContains($user_stat['Url']);
      $this->assertSession()->pageTextContains($user_stat['Flavor']);
      $this->assertSession()->pageTextContains($user_stat['Priority']);
    }
  }

  /**
   * @Given /^I click on the "([^"]*)" link$/
   *
   * @param mixed $arg1
   */
  public function iClickOnTheLink($arg1): void
  {
    $page = $this->getSession()->getPage();
    $link = $page->findLink($arg1);
    $link->click();
  }

  /**
   * @Then /^I write "([^"]*)" in textarea with label "([^"]*)"$/
   *
   * @param string $arg1
   * @param string $arg2
   */
  public function iWriteInTextareaWithLabel($arg1, $arg2): void
  {
    $textarea = $this->getSession()->getPage()->findField($arg2);
    Assert::assertNotNull($textarea, 'Textarea not found');
    $textarea->setValue($arg1);
  }

  /**
   * @Then /^I click on the button named "([^"]*)"/
   *
   * @param mixed $arg1
   *
   * @throws ElementNotFoundException
   */
  public function iClickOnTheButton($arg1): void
  {
    $this->assertSession()->elementExists('named', ['button', $arg1]);

    $this
      ->getSession()
      ->getPage()
      ->find('named', ['button', $arg1])
      ->click()
    ;
  }

  /**
   * @Then /^I should see the user table:$/
   */
  public function iShouldSeeTheUserTable(TableNode $table): void
  {
    $user_stats = $table->getHash();
    foreach ($user_stats as $user_stat) {
      $this->assertSession()->pageTextContains($user_stat['username']);
      $this->assertSession()->pageTextContains($user_stat['email']);
      $this->assertSession()->pageTextContains($user_stat['groups']);
      $this->assertSession()->pageTextContains($user_stat['enabled']);
      $this->assertSession()->pageTextContains($user_stat['createdAt']);
    }
  }

  // --------------------------------------------------------------------------------------------------------------------
  //  User Agent
  // --------------------------------------------------------------------------------------------------------------------

  protected function iUseTheUserAgent(string $user_agent): void
  {
    $this->getSession()->setRequestHeader('User-Agent', $user_agent);
  }

  protected function iUseTheUserAgentParameterized(string $lang_version, string $flavor, string $app_version, string $build_type, string $theme = 'pocketcode'): void
  {
    // see org.catrobat.catroid.ui.WebViewActivity
    $platform = 'Android';
    $user_agent = 'Catrobat/'.$lang_version.' '.$flavor.'/'.$app_version.' Platform/'.$platform.
      ' BuildType/'.$build_type.' Theme/'.$theme;
    $this->iUseTheUserAgent($user_agent);
  }

  /**
   * @param mixed $path
   * @param mixed $filename
   * @param mixed $size
   */
  protected function generateFileInPath($path, $filename, $size): void
  {
    $full_filename = $path.'/'.$filename;
    $dirname = dirname($full_filename);
    if (!is_dir($dirname)) {
      mkdir($dirname, 0755, true);
    }
    $file_path = fopen($full_filename, 'w'); // open in write mode.
    fseek($file_path, $size - 1, SEEK_CUR); // seek to SIZE-1
    fwrite($file_path, 'a'); // write a dummy char at SIZE position
    fclose($file_path); // close the file.
  }

  /**
   * @param mixed $option
   *
   * @throws Exception
   */
  protected function iSelectTheOptionInThePopup($option): void
  {
    $page = $this->getSession()->getPage();
    // click the input on the popup to show yes or no option
    $page
      ->find('css', '.editable-input')
      ->click()
  ;

    // click yes or no option
    if ('yes' == $option) {
      $page
        ->find('css', 'select.form-control > option:nth-child(2)')
        ->click()
    ;
    } else {
      $page
        ->find('css', 'select.form-control > option:nth-child(1)')
        ->click()
    ;
    }
    // click button to confirm the selection
    $page
      ->find('css', 'button.btn-sm:nth-child(1)')
      ->click()
  ;
  }
}
