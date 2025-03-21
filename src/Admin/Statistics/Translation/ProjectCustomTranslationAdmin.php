<?php

namespace App\Admin\Statistics\Translation;

use App\DB\Entity\Project\Program;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class ProjectCustomTranslationAdmin extends AbstractAdmin
{
  /**
   * {@inheritdoc}
   */
  protected $baseRouteName = 'admin_catrobat_adminbundle_project_custom_translation';

  /**
   * {@inheritdoc}
   */
  protected $baseRoutePattern = 'project_custom_translation';

  /**
   * {@inheritDoc}
   */
  protected function configureExportFields(): array
  {
    return ['id', 'project.id', 'language', 'name', 'description', 'credits'];
  }

  /**
   * {@inheritdoc}
   *
   * Fields to be shown on lists
   */
  protected function configureListFields(ListMapper $list): void
  {
    $list
      ->add('id')
      ->add('project', null, ['admin_code' => 'admin.block.projects.overview'])
      ->add('language')
      ->add('name')
      ->add('description')
      ->add('credits')
      ->add(ListMapper::NAME_ACTIONS, null, [
        'actions' => [
          'edit' => [],
          'delete' => [],
        ],
      ])
    ;
  }

  /**
   * {@inheritdoc}
   *
   * Fields to be shown on create/edit forms
   */
  protected function configureFormFields(FormMapper $form): void
  {
    $form
      ->add('project', EntityType::class, ['class' => Program::class], [
        'admin_code' => 'admin.block.projects.overview',
      ])
      ->add('language')
      ->add('name')
      ->add('description')
      ->add('credits')
    ;
  }

  protected function configureRoutes(RouteCollectionInterface $collection): void
  {
    $collection->remove('acl');
  }
}
