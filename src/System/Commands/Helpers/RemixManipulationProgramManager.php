<?php

namespace App\System\Commands\Helpers;

use App\Project\ProgramManager;

class RemixManipulationProgramManager extends ProgramManager
{
  /**
   * @param mixed $remix_graph_mapping
   */
  public function useRemixManipulationFileExtractor($remix_graph_mapping): void
  {
    $old_file_extractor = $this->file_extractor;
    $this->file_extractor = new RemixManipulationCatrobatFileExtractor(
      $remix_graph_mapping,
      $old_file_extractor->getExtractDir(),
      $old_file_extractor->getExtractPath()
    );
  }
}
