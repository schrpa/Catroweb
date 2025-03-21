<?php

namespace App\Admin\ApkGeneration;

use App\DB\Entity\Project\Program;
use App\Storage\ScreenshotRepository;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Sonata\Form\Type\DateTimeRangePickerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType as SymfonyChoiceType;

class ApkPendingAdmin extends AbstractAdmin
{
  /**
   * {@inheritdoc}
   */
  protected $baseRouteName = 'admin_catrobat_apk_pending';

  /**
   * {@inheritdoc}
   */
  protected $baseRoutePattern = 'apk_pending';

  /**
   * {@inheritDoc}
   */
  protected function configureDefaultSortValues(array &$sortValues): void
  {
    $sortValues[DatagridInterface::SORT_BY] = 'apk_request_time';
    $sortValues[DatagridInterface::SORT_ORDER] = 'DESC';
  }

  public function __construct(
        private readonly ScreenshotRepository $screenshot_repository
    ) {
  }

  /**
   * @param Program $object
   */
  public function getThumbnailImageUrl($object): string
  {
    return '/'.$this->screenshot_repository->getThumbnailWebPath($object->getId());
  }

  protected function configureQuery(ProxyQueryInterface $query): ProxyQueryInterface
  {
    /** @var ProxyQuery $query */
    $query = parent::configureQuery($query);

    $qb = $query->getQueryBuilder();

    $qb->andWhere(
      $qb->expr()->eq($qb->getRootAliases()[0].'.apk_status', ':apk_status')
    );
    $qb->setParameter('apk_status', Program::APK_PENDING);

    return $query;
  }

  /**
   * {@inheritdoc}
   *
   * Fields to be shown on filter forms
   */
  protected function configureDatagridFilters(DatagridMapper $filter): void
  {
    $filter
      ->add('id')
      ->add('user.username', null, ['label' => 'User'])
      ->add('name')
      ->add('apk_request_time', DateTimeRangeFilter::class,
        [
          'field_type' => DateTimeRangePickerType::class,
        ])
      ->add('apk_status',null,
        [
          'field_type' => SymfonyChoiceType::class,
          'field_options' => ['choices' => ['None' => Program::APK_NONE, 'Pending' => Program::APK_PENDING, 'Ready' => Program::APK_READY]],
        ])
    ;
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
      ->add('user', null, [
        'route' => [
          'name' => 'show',
        ],
      ])
      ->add('name')
      ->add('apk_request_time')
      ->add('thumbnail', 'string', [
        'accessor' => fn ($subject): string => $this->getThumbnailImageUrl($subject),
        'template' => 'Admin/program_thumbnail_image_list.html.twig',
      ])
      ->add('apk_status', 'choice', [
        'choices' => [
          Program::APK_NONE => 'None',
          Program::APK_PENDING => 'Pending',
          Program::APK_READY => 'Ready',
        ], ])
      ->add(ListMapper::NAME_ACTIONS, null, [
        'actions' => [
          'Reset' => [
            'template' => 'Admin/CRUD/list__action_reset_status.html.twig',
          ],
          'Rebuild' => [
            'template' => 'Admin/CRUD/list__action_rebuild_apk.html.twig',
          ],
        ],
      ])
    ;
  }

  protected function configureRoutes(RouteCollectionInterface $collection): void
  {
    $collection->add('resetApkBuildStatus', $this->getRouterIdParameter().'/resetApkBuildStatus');
    $collection->add('requestApkRebuild', $this->getRouterIdParameter().'/requestApkRebuild');
    $collection->add('rebuildAllApk');
    $collection->add('resetPendingProjects');
    $collection->remove('create')->remove('delete')->remove('export');
  }
}
