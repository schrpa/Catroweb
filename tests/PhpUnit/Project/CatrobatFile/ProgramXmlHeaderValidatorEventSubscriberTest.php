<?php

namespace Tests\PhpUnit\Project\CatrobatFile;

use App\Project\CatrobatFile\ExtractedCatrobatFile;
use App\Project\CatrobatFile\InvalidCatrobatFileException;
use App\Project\CatrobatFile\ProgramXmlHeaderValidatorEventSubscriber;
use App\System\Testing\PhpUnit\Hook\RefreshTestEnvHook;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers  \App\Project\CatrobatFile\ProgramXmlHeaderValidatorEventSubscriber
 */
class ProgramXmlHeaderValidatorEventSubscriberTest extends TestCase
{
  private ProgramXmlHeaderValidatorEventSubscriber $program_xml_header_validator;

  protected function setUp(): void
  {
    $this->program_xml_header_validator = new ProgramXmlHeaderValidatorEventSubscriber();
  }

  public function testInitialization(): void
  {
    $this->assertInstanceOf(ProgramXmlHeaderValidatorEventSubscriber::class, $this->program_xml_header_validator);
  }

  public function testChecksIfTheProgramXmlHeaderIsValid(): void
  {
    $file = $this->createMock(ExtractedCatrobatFile::class);
    $xml = simplexml_load_file(RefreshTestEnvHook::$GENERATED_FIXTURES_DIR.'base/code.xml');
    $file->expects($this->atLeastOnce())->method('getProgramXmlProperties')->willReturn($xml);
    $this->program_xml_header_validator->validate($file);
  }

  public function testThrowsAnExceptionIfHeaderIsMissing(): void
  {
    $file = $this->createMock(ExtractedCatrobatFile::class);
    $xml = simplexml_load_file(RefreshTestEnvHook::$GENERATED_FIXTURES_DIR.'base/code.xml');
    unset($xml->header);
    $file->expects($this->atLeastOnce())->method('getProgramXmlProperties')->willReturn($xml);
    $this->expectException(InvalidCatrobatFileException::class);
    $this->program_xml_header_validator->validate($file);
  }

  public function testThrowsAnExceptionIfHeaderInformationIsMissing(): void
  {
    $file = $this->createMock(ExtractedCatrobatFile::class);
    $xml = simplexml_load_file(RefreshTestEnvHook::$GENERATED_FIXTURES_DIR.'base/code.xml');
    unset($xml->header->applicationName);
    $file->expects($this->atLeastOnce())->method('getProgramXmlProperties')->willReturn($xml);
    $this->expectException(InvalidCatrobatFileException::class);
    $this->program_xml_header_validator->validate($file);
  }

  public function testChecksIfProgramNameIsSet(): void
  {
    $file = $this->createMock(ExtractedCatrobatFile::class);
    $xml = simplexml_load_file(RefreshTestEnvHook::$GENERATED_FIXTURES_DIR.'/base/code.xml');
    unset($xml->header->programName);
    $file->expects($this->atLeastOnce())->method('getProgramXmlProperties')->willReturn($xml);
    $this->expectException(InvalidCatrobatFileException::class);
    $this->program_xml_header_validator->validate($file);
  }

  public function testChecksIfDescriptionIsSet(): void
  {
    $file = $this->createMock(ExtractedCatrobatFile::class);
    $xml = simplexml_load_file(RefreshTestEnvHook::$GENERATED_FIXTURES_DIR.'/base/code.xml');
    unset($xml->header->description);
    $file->expects($this->atLeastOnce())->method('getProgramXmlProperties')->willReturn($xml);
    $this->expectException(InvalidCatrobatFileException::class);
    $this->program_xml_header_validator->validate($file);
  }
}
