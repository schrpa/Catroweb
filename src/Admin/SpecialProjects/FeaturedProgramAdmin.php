<?php

namespace App\Admin\SpecialProjects;

use App\Admin\SpecialProjects\Forms\FeaturedImageConstraint;
use App\DB\Entity\Flavor;
use App\DB\Entity\Project\Program;
use App\DB\Entity\Project\Special\FeaturedProgram;
use App\Project\ProgramManager;
use App\Storage\ImageRepository;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Object\Metadata;
use Sonata\AdminBundle\Object\MetadataInterface;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormError;

class FeaturedProgramAdmin extends AbstractAdmin
{
  /**
   * {@inheritdoc}
   */
  protected $baseRouteName = 'adminfeatured_program';

  /**
   * {@inheritdoc}
   */
  protected $baseRoutePattern = 'featured_program';

  public function __construct(
      private readonly ImageRepository $featured_image_repository,
      private readonly ProgramManager $program_manager
  ) {
  }

  /**
   * @param FeaturedProgram $object
   *
   * @return string
   */
  public function getFeaturedImageUrl($object)
  {
    return '../../'.$this->featured_image_repository->getWebPath($object->getId(), $object->getImageType(), true);
  }

  /**
   * {@inheritdoc}
   */
  public function getObjectMetadata($object): MetadataInterface
  {
    /** @var FeaturedProgram $featured_program */
    $featured_program = $object;

    return new Metadata($featured_program->getProgram()->getName(), $featured_program->getProgram()->getDescription(),
      $this->getFeaturedImageUrl($featured_program));
  }

  /**
   * {@inheritdoc}
   */
  public function preUpdate(object $object): void
  {
    /** @var FeaturedProgram $featured_program */
    $featured_program = $object;

    $featured_program->old_image_type = $featured_program->getImageType();
  }

  /**
   * {@inheritdoc}
   */
  public function preValidate(object $object): void
  {
    $id = $this->getForm()->get('Program_Id_or_Url')->getData();

    if ($this->getForm()->get('Use_Url')->getData()) {
      if (filter_var($id, FILTER_VALIDATE_URL)) {
        $object->setUrl($id);
        if (null !== $object->getId()) {
          $object->setProgram(null);
        }
      } else {
        $this->getForm()->addError(new FormError('Please enter a valid URL.'));
      }
    } else {
      if (null !== $id) {
        $id = preg_replace('$(.*)/project/$', '', $id);
      }

      $program = $this->program_manager->find($id);

      if (null !== $program) {
        $object->setProgram($program);
        if (null !== $object->getURL()) {
          $object->setURL(null);
        }
      } else {
        $this->getForm()->addError(new FormError('Unable to find program with given ID.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Fields to be shown on create/edit forms
   */
  protected function configureFormFields(FormMapper $form): void
  {
    /** @var FeaturedProgram $featured_project */
    $featured_project = $this->getSubject();
    $file_options = [
      'required' => (null === $featured_project->getId()),
      'constraints' => [
        new FeaturedImageConstraint(),
      ],
    ];

    $id_value = '';
    $use_url = false;

    if (null !== $this->getSubject()->getId()) {
      $file_options['help'] = '<img src="../'.$this->getFeaturedImageUrl($featured_project).'">';

      $id_value = $this->getSubject()->getUrl();
      $use_url = true;
      if (null == $id_value && !is_null($this->getSubject()->getProgram())) {
        $id_value = $this->getSubject()->getProgram()->getId();
        $use_url = false;
      }
    } else {
      $file_options = ['help' => 'The featured image must be of size 1024 x 400'];
    }
    $form
      ->add('file', FileType::class, $file_options)
      ->add('Use_Url', CheckboxType::class, ['mapped' => false, 'required' => false,
        'help' => 'Toggle to save URL instead of Program ID.', 'data' => $use_url, ])
      ->add('Program_Id_or_Url', TextType::class, ['mapped' => false, 'data' => $id_value])
      ->add('flavor', null, ['class' => Flavor::class, 'multiple' => false, 'required' => true])
      ->add('priority')
      ->add('for_ios', null, ['label' => 'iOS only', 'required' => false,
        'help' => 'Toggle for iOS featured programs api call.', ])
      ->add('active', null, ['required' => false])
    ;
  }

  /**
   * {@inheritdoc}
   *
   * Fields to be shown on filter forms
   */
  protected function configureDatagridFilters(DatagridMapper $filter): void
  {
    $filter
      ->add('program.name')
      ->add('for_ios')
      ->add('active')
      ->add('priority')
      ->add('flavor')
    ;
  }

  /**
   * {@inheritdoc}
   *
   * Fields to be shown on lists
   */
  protected function configureListFields(ListMapper $list): void
  {
    unset($this->getListModes()['mosaic']);
    $list
      ->addIdentifier('id', null, [
        'sortable' => false,
      ])
      ->add('Featured Image', null, [
        'accessor' => fn ($subject): string => $this->getFeaturedImageUrl($subject),
        'template' => 'Admin/featured_image.html.twig',
      ])
      ->add('program', EntityType::class, [
        'class' => Program::class,
        'editable' => false,
      ])
      ->add('url', UrlType::class)
      ->add('flavor', 'string', [
        'sortable' => false,
      ])
      ->add('priority', 'integer')
      ->add('for_ios', null, ['label' => 'iOS only'])
      ->add('active')
      ->add(ListMapper::NAME_ACTIONS, null, [
        'actions' => [
          'edit' => [],
          'delete' => [],
        ],
      ])
    ;
  }

  protected function configureRoutes(RouteCollectionInterface $collection): void
  {
    $collection->remove('acl');
  }
}
