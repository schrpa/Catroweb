<?php

namespace App\System\Commands\ImportProjects;

use App\DB\Entity\User\User;
use App\Project\AddProgramRequest;
use App\Project\CatrobatFile\InvalidCatrobatFileException;
use App\Project\Remix\RemixGraphLayout;
use App\System\Commands\Helpers\RemixManipulationProgramManager;
use App\User\UserManager;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\File\File;

class ProgramImportCommand extends Command
{
  final public const REMIX_GRAPH_NO_LAYOUT = '0';

  public function __construct(private readonly UserManager $user_manager, private readonly RemixManipulationProgramManager $remix_manipulation_program_manager)
  {
    parent::__construct();
  }

  protected function configure(): void
  {
    $this->setName('catrobat:import')
      ->setDescription('Import programs from a given directory to the application')
      ->addArgument('directory', InputArgument::REQUIRED,
        'Directory containing catrobat files for import')
      ->addArgument('user', InputArgument::REQUIRED,
        'User who will be the owner of these programs')
      ->addOption('remix-layout', null, InputOption::VALUE_REQUIRED,
        'Generates remix graph based on given layout',
        self::REMIX_GRAPH_NO_LAYOUT)
    ;
  }

  /**
   * @throws Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $directory = $input->getArgument('directory');
    $username = $input->getArgument('user');
    $layout_idx = intval(($input->getOption('remix-layout') - 1));

    $all_layouts = RemixGraphLayout::$REMIX_GRAPH_MAPPING;
    $num_of_layouts = count($all_layouts);
    $remix_graph_mapping = (($layout_idx >= 0) && ($layout_idx < $num_of_layouts)) ? $all_layouts[$layout_idx] : [];

    $this->remix_manipulation_program_manager->useRemixManipulationFileExtractor($remix_graph_mapping);

    $finder = new Finder();
    $finder->files()->name('*.catrobat')->in($directory)->depth(0);

    if (0 == $finder->count()) {
      $output->writeln('No catrobat files found');

      return 1;
    }

    /** @var User|null $user */
    $user = $this->user_manager->findUserByUsername($username);
    if (null == $user) {
      $output->writeln('User '.$username.' was not found!');

      return 1;
    }

    /** @var SplFileInfo $file */
    foreach ($finder as $file) {
      try {
        $output->writeln('Importing file '.$file);
        $add_program_request = new AddProgramRequest($user, new File($file->__toString()));
        $program = $this->remix_manipulation_program_manager->addProgram($add_program_request);
        $program->setViews(random_int(0, 10));
        $output->writeln('Added program <'.$program->getName().'> for user: <'.$username.'>');
      } catch (InvalidCatrobatFileException $e) {
        $output->writeln('FAILED to add program!');
        $output->writeln($e->getMessage().' ('.$e->getCode().')');
      }
    }

    return 0;
  }
}
