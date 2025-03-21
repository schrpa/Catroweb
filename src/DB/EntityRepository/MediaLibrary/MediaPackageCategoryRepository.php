<?php

namespace App\DB\EntityRepository\MediaLibrary;

use App\DB\Entity\MediaLibrary\MediaPackageCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * Class MediaPackageCategoryRepository used for interacting with the database when handling MediaPackageCategories.
 */
class MediaPackageCategoryRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $manager_registry)
  {
    parent::__construct($manager_registry, MediaPackageCategory::class);
  }

  /**
   * Creates a new MediaPackageCategory.
   *
   * @param string          $name           The name
   * @param ArrayCollection $media_packages an ArrayCollection containing the MediaPackages this MediaPackageCategory belongs to
   *
   * @throws Exception when an error occurs during creating
   *
   * @return MediaPackageCategory the created MediaPackageCategory
   */
  public function createMediaPackageCategory(string $name, ArrayCollection $media_packages): MediaPackageCategory
  {
    $new_media_package_cat = new MediaPackageCategory();
    $new_media_package_cat->setName($name);
    $new_media_package_cat->setPackage($media_packages);

    $this->getEntityManager()->persist($new_media_package_cat);
    $this->getEntityManager()->flush();

    return $new_media_package_cat;
  }
}
