<?php

namespace App\Project\CatrobatCode\Parser;

use SimpleXMLElement;

class ParsedSceneProgram
{
  protected array $scenes = [];

  protected CodeStatistic $code_statistic;

  public function __construct(protected SimpleXMLElement $program_xml_properties)
  {
    $this->parseScenes();

    $this->code_statistic = new CodeStatistic();
    $this->computeCodeStatistic();
  }

  public function hasScenes(): bool
  {
    return true;
  }

  public function getScenes(): array
  {
    return $this->scenes;
  }

  public function getCodeStatistic(): CodeStatistic
  {
    return $this->code_statistic;
  }

  protected function parseScenes(): void
  {
    foreach ($this->program_xml_properties->scenes->scene as $scene_xml_properties) {
      $this->scenes[] = new ParsedScene($scene_xml_properties);
    }
  }

  protected function computeCodeStatistic(): void
  {
    foreach ($this->scenes as $scene) {
      $this->code_statistic->update($scene);
    }
    $this->code_statistic->computeVariableStatistic($this->program_xml_properties);
  }
}
