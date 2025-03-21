<?php

namespace App\Admin\Tools\Maintenance;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Route\RouteCollectionInterface;

class MaintainAdmin extends AbstractAdmin
{
  /**
   * {@inheritdoc}
   */
  protected $baseRoutePattern = 'maintain';

  /**
   * {@inheritdoc}
   */
  protected $baseRouteName = 'maintain';

  protected function configureRoutes(RouteCollectionInterface $collection): void
  {
    // Find the implementation in the Controller-Folder
    $collection->clearExcept(['list']);
    $collection->add('apk')
      ->add('compressed')
      ->add('archive_logs')
      ->add('delete_logs')
    ;
  }
}
