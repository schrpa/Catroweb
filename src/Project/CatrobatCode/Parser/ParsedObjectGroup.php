<?php

namespace App\Project\CatrobatCode\Parser;

use SimpleXMLElement;

class ParsedObjectGroup
{
  protected SimpleXMLElement $name;

  protected array $objects = [];

  public function __construct(protected SimpleXMLElement $object_group_xml_properties)
  {
    $this->name = $this->resolveName();
  }

  /**
   * @param mixed $object
   */
  public function addObject($object): void
  {
    $this->objects[] = $object;
  }

  public function getName(): SimpleXMLElement
  {
    return $this->name;
  }

  public function getObjects(): array
  {
    return $this->objects;
  }

  public function isGroup(): bool
  {
    return true;
  }

  private function resolveName(): SimpleXMLElement
  {
    if (null != $this->object_group_xml_properties[Constants::NAME_ATTRIBUTE]) {
      return $this->object_group_xml_properties[Constants::NAME_ATTRIBUTE];
    }

    return $this->object_group_xml_properties->name;
  }
}
