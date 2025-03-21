<?php

namespace App\Api\Services\Projects;

use App\Api\Services\Base\AbstractResponseManager;
use App\Api\Services\Base\TranslatorAwareTrait;
use App\Api\Services\ResponseCache\ResponseCacheManager;
use App\DB\Entity\Project\Extension;
use App\DB\Entity\Project\Program;
use App\DB\Entity\Project\Special\FeaturedProgram;
use App\DB\Entity\Project\Tag;
use App\Project\ProgramManager;
use App\Storage\ImageRepository;
use App\Utils\ElapsedTimeStringFormatter;
use DateTimeInterface;
use Exception;
use OpenAPI\Server\Model\FeaturedProjectResponse;
use OpenAPI\Server\Model\ProjectResponse;
use OpenAPI\Server\Model\ProjectsCategory;
use OpenAPI\Server\Model\TagResponse;
use OpenAPI\Server\Model\UpdateProjectFailureResponse;
use OpenAPI\Server\Model\UploadErrorResponse;
use OpenAPI\Server\Service\SerializerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProjectsResponseManager extends AbstractResponseManager
{
  use TranslatorAwareTrait;

  public function __construct(
    private readonly ElapsedTimeStringFormatter $time_formatter,
    private readonly ImageRepository $image_repository,
    private readonly UrlGeneratorInterface $url_generator,
    private readonly ParameterBagInterface $parameter_bag,
    TranslatorInterface $translator,
    SerializerInterface $serializer,
    private readonly ProgramManager $project_manager,
    ResponseCacheManager $response_cache_manager
  ) {
    parent::__construct($translator, $serializer, $response_cache_manager);
  }

  /**
   * @param ?string $attributes Comma-separated list of attributes to include into response
   */
  public function createProjectDataResponse(Program $program, ?string $attributes): ProjectResponse
  {
    if (empty($attributes)) {
      $attributes_list = ['id', 'name', 'author', 'views', 'downloads', 'flavor', 'uploaded_string', 'screenshot_large', 'screenshot_small', 'project_url'];
      $attributes_list[] = 'download'; // TODO: hotfix for Catty. Remove after Catty uses attributes-parameter.
      $attributes_list[] = 'tags'; // TODO: hotfix for Catty. Remove after Catty uses attributes-parameter.
    } elseif ('ALL' === $attributes) {
      $attributes_list = ['id', 'name', 'author', 'description', 'credits', 'version', 'views', 'downloads', 'reactions', 'comments', 'private', 'flavor', 'tags', 'uploaded', 'uploaded_string', 'screenshot_large', 'screenshot_small', 'project_url', 'download_url', 'filesize'];
      $attributes_list[] = 'download'; // TODO: hotfix for Catty. Remove after Catty uses attributes-parameter.
    } else {
      $attributes_list = explode(',', $attributes);
    }

    /** @var Program $project */
    $project = $program->isExample() ? $program->getProgram() : $program;

    $data = [];

    if (in_array('id', $attributes_list, true)) {
      $data['id'] = $project->getId();
    }
    if (in_array('name', $attributes_list, true)) {
      $data['name'] = $project->getName();
    }
    if (in_array('author', $attributes_list, true)) {
      $data['author'] = $project->getUser()->getUserIdentifier();
    }
    if (in_array('description', $attributes_list, true)) {
      $data['description'] = $project->getDescription() ?? '';
    }
    if (in_array('credits', $attributes_list, true)) {
      $data['credits'] = $project->getCredits() ?? '';
    }
    if (in_array('version', $attributes_list, true)) {
      $data['version'] = $project->getCatrobatVersionName();
    }
    if (in_array('views', $attributes_list, true)) {
      $data['views'] = $project->getViews();
    }
    if (in_array('download', $attributes_list, true)) {
      $data['download'] = $project->getDownloads(); // deprecated and will be removed
    }
    if (in_array('downloads', $attributes_list, true)) {
      $data['downloads'] = $project->getDownloads();
    }
    if (in_array('reactions', $attributes_list, true)) {
      $data['reactions'] = $project->getLikes()->count();
    }
    if (in_array('comments', $attributes_list, true)) {
      $data['comments'] = $project->getComments()->count();
    }
    if (in_array('private', $attributes_list, true)) {
      $data['private'] = $project->getPrivate();
    }
    if (in_array('flavor', $attributes_list, true)) {
      $data['flavor'] = $project->getFlavor() ?? '';
    }
    if (in_array('tags', $attributes_list, true)) {
      $tags = [];
      $project_tags = $project->getTags();
      /** @var Tag $tag */
      foreach ($project_tags as $tag) {
        $tags[$tag->getId()] = $tag->getInternalTitle();
      }
      $data['tags'] = $tags;
    }
    if (in_array('uploaded', $attributes_list, true)) {
      $data['uploaded'] = $project->getUploadedAt()->getTimestamp();
    }
    if (in_array('uploaded_string', $attributes_list, true)) {
      try {
        $data['uploaded_string'] = $this->time_formatter->format($project->getUploadedAt()->getTimestamp());
      } catch (Exception) {
        $data['uploaded_string'] = $project->getUploadedAt()->format(DateTimeInterface::RFC2822);
      }
    }
    if (in_array('screenshot_large', $attributes_list, true)) {
      $data['screenshot_large'] = $program->isExample() ? $this->image_repository->getAbsoluteWebPath($program->getId(), $program->getImageType(), false) : $this->project_manager->getScreenshotLarge($project->getId());
    }
    if (in_array('screenshot_small', $attributes_list, true)) {
      $data['screenshot_small'] = $program->isExample() ? $this->image_repository->getAbsoluteWebPath($program->getId(), $program->getImageType(), false) : $this->project_manager->getScreenshotSmall($project->getId());
    }
    if (in_array('project_url', $attributes_list, true)) {
      $data['project_url'] = ltrim($this->url_generator->generate(
        'program',
        [
          'theme' => $this->parameter_bag->get('umbrellaTheme'),
          'id' => $project->getId(),
        ],
        UrlGeneratorInterface::ABSOLUTE_URL), '/'
    );
    }
    if (in_array('download_url', $attributes_list, true)) {
      $data['download_url'] = ltrim($this->url_generator->generate(
        'open_api_server_projects_projectidcatrobatget',
        [
          'id' => $project->getId(),
        ],
        UrlGeneratorInterface::ABSOLUTE_URL), '/');
    }
    if (in_array('filesize', $attributes_list, true)) {
      $data['filesize'] = ($project->getFilesize() / 1_048_576);
    }

    return new ProjectResponse($data);
  }

  public function createProjectsDataResponse(array $projects, ?string $attributes = null): array
  {
    $response = [];
    foreach ($projects as $project) {
      $response[] = $this->createProjectDataResponse($project, $attributes);
    }

    return $response;
  }

  public function createFeaturedProjectResponse(FeaturedProgram $featured_project, ?string $attributes = null): FeaturedProjectResponse
  {
    if (empty($attributes) || 'ALL' === $attributes) {
      $attributes_list = ['id', 'project_id', 'project_url', 'url', 'name', 'author', 'featured_image'];
    } else {
      $attributes_list = explode(',', $attributes);
    }

    $data = [];
    if (in_array('id', $attributes_list, true)) {
      $data['id'] = $featured_project->getId() ?? -1;
    }
    if (in_array('project_id', $attributes_list, true)) {
      $data['project_id'] = $featured_project->getProgram()->getId() ?? '';
    }
    if (in_array('name', $attributes_list, true)) {
      $data['name'] = $featured_project->getProgram()->getName();
    }
    if (in_array('author', $attributes_list, true)) {
      $data['author'] = $featured_project->getProgram()->getUser()->getUserIdentifier();
    }
    if (in_array('featured_image', $attributes_list, true)) {
      $data['featured_image'] = $this->image_repository->getAbsoluteWebPath($featured_project->getId(), $featured_project->getImageType(), true);
    }

    if (in_array('url', $attributes_list, true) || in_array('project_url', $attributes_list, true)) {
      $url = $featured_project->getUrl();
      $project_url = null;
      if (empty($url)) {
        $url = $project_url = ltrim($this->url_generator->generate(
            'program',
            [
              'theme' => $this->parameter_bag->get('umbrellaTheme'),
              'id' => $featured_project->getProgram()->getId(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL), '/'
        );
      }

      if (in_array('project_url', $attributes_list, true)) {
        $data['project_url'] = $project_url;
      }
      if (in_array('url', $attributes_list, true)) {
        $data['url'] = $url;
      }
    }

    return new FeaturedProjectResponse($data);
  }

  public function createFeaturedProjectsResponse(array $featured_projects, ?string $attributes = null): array
  {
    $response = [];

    /** @var FeaturedProgram $featured_project */
    foreach ($featured_projects as $featured_project) {
      $response[] = $this->createFeaturedProjectResponse($featured_project, $attributes);
    }

    return $response;
  }

  public function createProjectCategoryResponse(array $projects, string $category, string $locale, ?string $attributes = null): ProjectsCategory
  {
    return new ProjectsCategory([
      'projects_list' => $this->createProjectsDataResponse($projects, $attributes),
      'type' => $category,
      'name' => $this->__('category.'.$category, [], $locale),
    ]);
  }

  public function createProjectLocation(Program $project): string
  {
    return $this->url_generator->generate(
      'program',
      [
        'theme' => $this->parameter_bag->get('umbrellaTheme'),
        'id' => $project->getId(),
      ],
      UrlGeneratorInterface::ABSOLUTE_URL);
  }

  public function createUploadErrorResponse(string $locale): UploadErrorResponse
  {
    return new UploadErrorResponse([
      'error' => $this->__('api.projectsPost.creating_error', [], $locale),
    ]);
  }

  public function createProjectsExtensionsResponse(array $extensions, string $locale): array
  {
    $response = [];

    /** @var Extension $extension */
    foreach ($extensions as $extension) {
      $response[] = $this->createExtensionResponse($extension, $locale);
    }

    return $response;
  }

  public function createExtensionResponse(Extension $extension, string $locale): TagResponse
  {
    return new TagResponse([
      'id' => $extension->getInternalTitle(),
      'text' => $this->__($extension->getTitleLtmCode(), [], $locale),
    ]);
  }

  public function createProjectsTagsResponse(array $tags, string $locale): array
  {
    $response = [];
    /** @var Tag $tag */
    foreach ($tags as $tag) {
      $response[] = $this->createTagResponse($tag, $locale);
    }

    return $response;
  }

  public function createTagResponse(Tag $tag, string $locale): TagResponse
  {
    return new TagResponse([
      'id' => $tag->getInternalTitle(),
      'text' => $this->__($tag->getTitleLtmCode(), [], $locale),
    ]);
  }

  public function createProjectCatrobatFileResponse(string $id, File $file): BinaryFileResponse
  {
    $response = new BinaryFileResponse($file);
    $response->headers->set(
      'Content-Disposition',
      'attachment; filename="'.$id.'.catrobat"'
    );

    return $response;
  }

  public function createUpdateFailureResponse(int $failure, string $locale): UpdateProjectFailureResponse
  {
    if (ProjectsApiProcessor::SERVER_ERROR_SAVE_XML === $failure) {
      return new UpdateProjectFailureResponse([
        'error' => $this->__('api.updateProject.xmlError', [], $locale),
      ]);
    }
    if (ProjectsApiProcessor::SERVER_ERROR_SCREENSHOT === $failure) {
      return new UpdateProjectFailureResponse([
        'error' => $this->__('api.updateProject.screenshotError', [], $locale),
      ]);
    }

    return new UpdateProjectFailureResponse();
  }
}
