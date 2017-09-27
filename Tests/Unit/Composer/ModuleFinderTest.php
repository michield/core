<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Composer;

use Composer\Package\PackageInterface;
use PhpList\PhpList4\Composer\ModuleFinder;
use PhpList\PhpList4\Composer\PackageRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ProphecySubjectInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ModuleFinderTest extends TestCase
{
    /**
     * @var string
     */
    const YAML_COMMENT = '# This file is autogenerated. Please do not edit.';

    /**
     * @var ModuleFinder
     */
    private $subject = null;

    /**
     * @var PackageRepository|ProphecySubjectInterface
     */
    private $packageRepositoryProphecy = null;

    protected function setUp()
    {
        $this->subject = new ModuleFinder();

        $this->packageRepositoryProphecy = $this->prophesize(PackageRepository::class);

        /** @var PackageRepository|ProphecySubjectInterface $packageRepository */
        $packageRepository = $this->packageRepositoryProphecy->reveal();
        $this->subject->injectPackageRepository($packageRepository);
    }

    /**
     * @test
     */
    public function findBundleClassesForNoModulesReturnsEmptyArray()
    {
        $this->packageRepositoryProphecy->findModules()->willReturn([]);

        $result = $this->subject->findBundleClasses();

        self::assertSame([], $result);
    }

    /**
     * @return PackageInterface[][]
     */
    public function modulesWithoutBundlesDataProvider(): array
    {
        /** @var array[][] $extras */
        $extrasSets = [
            'one module without/with empty extras' => [[]],
            'one module with extras for other stuff' => [['branch-alias' => ['dev-master' => '4.0.x-dev']]],
            'one module with empty "phplist/phplist4-core" extras section' => [['phplist/phplist4-core' => []]],
            'one module with empty bundles extras section' => [['phplist/phplist4-core' => ['bundles' => []]]],
        ];

        return $this->buildMockPackagesWithModuleConfiguration($extrasSets);
    }

    /**
     * @param array[][] $extrasSets
     *
     * @return PackageInterface[][]
     */
    private function buildMockPackagesWithModuleConfiguration(array $extrasSets): array
    {
        $moduleSets = [];
        foreach ($extrasSets as $packageName => $extrasSet) {
            $moduleSet = $this->buildSingleMockPackageWithModuleConfiguration($extrasSet);
            $moduleSets[$packageName] = [$moduleSet];
        }

        return $moduleSets;
    }

    /**
     * @param array[] $extrasSet
     *
     * @return PackageInterface[]
     */
    private function buildSingleMockPackageWithModuleConfiguration(array $extrasSet): array
    {
        /** @var PackageInterface[] $moduleSet */
        $moduleSet = [];
        foreach ($extrasSet as $key => $extras) {
            /** @var PackageInterface|ProphecySubjectInterface $packageProphecy */
            $packageProphecy = $this->prophesize(PackageInterface::class);
            $packageProphecy->getExtra()->willReturn($extras);
            $packageProphecy->getName()->willReturn('phplist/test');
            $moduleSet[] = $packageProphecy->reveal();
        }
        return $moduleSet;
    }

    /**
     * @test
     * @param PackageInterface[] $modules
     * @dataProvider modulesWithoutBundlesDataProvider
     */
    public function findBundleClassesForModulesWithoutBundlesReturnsEmptyArray(array $modules)
    {
        $this->packageRepositoryProphecy->findModules()->willReturn($modules);

        $result = $this->subject->findBundleClasses();

        self::assertSame([], $result);
    }

    /**
     * @return PackageInterface[][]
     */
    public function modulesWithInvalidBundlesDataProvider(): array
    {
        /** @var array[][] $extras */
        $extrasSets = [
            'one module with phplist4-core section as string' => [['phplist/phplist4-core' => 'foo']],
            'one module with phplist4-core section as int' => [['phplist/phplist4-core' => 42]],
            'one module with phplist4-core section as float' => [['phplist/phplist4-core' => 3.14159]],
            'one module with phplist4-core section as bool' => [['phplist/phplist4-core' => true]],
            'one module with bundles section as string' => [['phplist/phplist4-core' => ['bundles' => 'foo']]],
            'one module with bundles section as int' => [['phplist/phplist4-core' => ['bundles' => 42]]],
            'one module with bundles section as float' => [['phplist/phplist4-core' => ['bundles' => 3.14159]]],
            'one module with bundles section as bool' => [['phplist/phplist4-core' => ['bundles' => true]]],
            'one module with one bundle class name as array' => [['phplist/phplist4-core' => ['bundles' => [[]]]]],
            'one module with one bundle class name as int' => [['phplist/phplist4-core' => ['bundles' => [42]]]],
            'one module with one bundle class name as float' => [['phplist/phplist4-core' => ['bundles' => [3.14159]]]],
            'one module with one bundle class name as bool' => [['phplist/phplist4-core' => ['bundles' => [true]]]],
            'one module with one bundle class name as null' => [['phplist/phplist4-core' => ['bundles' => [null]]]],
        ];

        return $this->buildMockPackagesWithModuleConfiguration($extrasSets);
    }

    /**
     * @test
     * @param PackageInterface[] $modules
     * @dataProvider modulesWithInvalidBundlesDataProvider
     */
    public function findBundleClassesForModulesWithInvalidBundlesConfigurationThrowsException(array $modules)
    {
        $this->packageRepositoryProphecy->findModules()->willReturn($modules);

        $this->expectException(\InvalidArgumentException::class);

        $this->subject->findBundleClasses();
    }

    /**
     * @return array[]
     */
    public function modulesWithBundlesDataProvider(): array
    {
        /** @var array[][] $dataSets */
        $dataSets = [
            'one module with one bundle' => [
                [
                    'phplist/foo' => [
                        'phplist/phplist4-core' => [
                            'bundles' => ['Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'],
                        ],
                    ],
                ],
                ['phplist/foo' => ['Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle']],
            ],
            'one module with two bundles' => [
                [
                    'phplist/foo' => [
                        'phplist/phplist4-core' => [
                            'bundles' => [
                                'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle',
                                'PhpList\\PhpList4\\ApplicationBundle\\PhpListApplicationBundle',
                            ],
                        ],
                    ],
                ],
                [
                    'phplist/foo' => [
                        'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle',
                        'PhpList\\PhpList4\\ApplicationBundle\\PhpListApplicationBundle',
                    ],
                ],
            ],
            'two module with one bundle each' => [
                [
                    'phplist/foo' => [
                        'phplist/phplist4-core' => [
                            'bundles' => ['Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'],
                        ],
                    ],
                    'phplist/bar' => [
                        'phplist/phplist4-core' => [
                            'bundles' => ['PhpList\\PhpList4\\ApplicationBundle\\PhpListApplicationBundle'],
                        ],
                    ],
                ],
                [
                    'phplist/foo' => ['Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'],
                    'phplist/bar' => ['PhpList\\PhpList4\\ApplicationBundle\\PhpListApplicationBundle'],
                ],
            ],
        ];

        $moduleSets = [];
        /** @var array[] $dataSet */
        foreach ($dataSets as $dataSetName => $dataSet) {
            /** @var string[][][] $extraSets */
            /** @var string[][] $expectedBundles */
            list($extraSets, $expectedBundles) = $dataSet;

            $testCases = [];
            foreach ($extraSets as $packageName => $extraSet) {
                /** @var PackageInterface|ProphecySubjectInterface $packageProphecy */
                $packageProphecy = $this->prophesize(PackageInterface::class);
                $packageProphecy->getExtra()->willReturn($extraSet);
                $packageProphecy->getName()->willReturn($packageName);
                $testCases[] = $packageProphecy->reveal();
            }
            $moduleSets[$dataSetName] = [$testCases, $expectedBundles];
        }

        return $moduleSets;
    }

    /**
     * @test
     * @param PackageInterface[] $modules
     * @param string[][] $expectedBundles
     * @dataProvider modulesWithBundlesDataProvider
     */
    public function findBundleClassesForModulesWithBundlesReturnsBundleClassNames(
        array $modules,
        array $expectedBundles
    ) {
        $this->packageRepositoryProphecy->findModules()->willReturn($modules);

        $result = $this->subject->findBundleClasses();

        self::assertSame($expectedBundles, $result);
    }

    /**
     * @test
     */
    public function createBundleConfigurationYamlForNoModulesReturnsCommentOnly()
    {
        $this->packageRepositoryProphecy->findModules()->willReturn([]);

        $result = $this->subject->createBundleConfigurationYaml();

        self::assertSame(self::YAML_COMMENT . "\n{  }", $result);
    }

    /**
     * @test
     * @param PackageInterface[][] $modules
     * @param array[] $bundles
     * @dataProvider modulesWithBundlesDataProvider
     */
    public function createBundleConfigurationYamlReturnsYamlForBundles(array $modules, array $bundles)
    {
        $this->packageRepositoryProphecy->findModules()->willReturn($modules);

        $result = $this->subject->createBundleConfigurationYaml();

        self::assertSame(self::YAML_COMMENT . "\n" . Yaml::dump($bundles), $result);
    }

    /**
     * @return PackageInterface[][]
     */
    public function modulesWithoutRoutesDataProvider(): array
    {
        /** @var array[][] $extras */
        $extrasSets = [
            'one module without/with empty extras' => [[]],
            'one module with extras for other stuff' => [['branch-alias' => ['dev-master' => '4.0.x-dev']]],
            'one module with empty "phplist/phplist4-core" extras section' => [['phplist/phplist4-core' => []]],
            'one module with empty routes extras section' => [['phplist/phplist4-core' => ['routes' => []]]],
        ];

        return $this->buildMockPackagesWithModuleConfiguration($extrasSets);
    }

    /**
     * @test
     * @param PackageInterface[] $modules
     * @dataProvider modulesWithoutRoutesDataProvider
     */
    public function findRoutesForModulesWithoutRoutesReturnsEmptyArray(array $modules)
    {
        $this->packageRepositoryProphecy->findModules()->willReturn($modules);

        $result = $this->subject->findRoutes();

        self::assertSame([], $result);
    }

    /**
     * @return PackageInterface[][]
     */
    public function modulesWithInvalidRoutesDataProvider(): array
    {
        /** @var array[][] $extras */
        $extrasSets = [
            'one module with phplist4-core section as string' => [['phplist/phplist4-core' => 'foo']],
            'one module with phplist4-core section as int' => [['phplist/phplist4-core' => 42]],
            'one module with phplist4-core section as float' => [['phplist/phplist4-core' => 3.14159]],
            'one module with phplist4-core section as bool' => [['phplist/phplist4-core' => true]],
            'one module with routes section as string' => [['phplist/phplist4-core' => ['routes' => 'foo']]],
            'one module with routes section as int' => [['phplist/phplist4-core' => ['routes' => 42]]],
            'one module with routes section as float' => [['phplist/phplist4-core' => ['routes' => 3.14159]]],
            'one module with routes section as bool' => [['phplist/phplist4-core' => ['routes' => true]]],
            'one module with one route class name as string' => [['phplist/phplist4-core' => ['routes' => ['foo']]]],
            'one module with one route class name as int' => [['phplist/phplist4-core' => ['routes' => [42]]]],
            'one module with one route class name as float' => [['phplist/phplist4-core' => ['routes' => [3.14159]]]],
            'one module with one route class name as bool' => [['phplist/phplist4-core' => ['routes' => [true]]]],
            'one module with one route class name as null' => [['phplist/phplist4-core' => ['routes' => [null]]]],
        ];

        return $this->buildMockPackagesWithModuleConfiguration($extrasSets);
    }

    /**
     * @test
     * @param PackageInterface[] $modules
     * @dataProvider modulesWithInvalidRoutesDataProvider
     */
    public function findRoutesClassesForModulesWithInvalidRoutesConfigurationThrowsException(array $modules)
    {
        $this->packageRepositoryProphecy->findModules()->willReturn($modules);

        $this->expectException(\InvalidArgumentException::class);

        $this->subject->findRoutes();
    }

    /**
     * @return array[]
     */
    public function modulesWithRoutesDataProvider(): array
    {
        /** @var array[][] $dataSets */
        $dataSets = [
            'one module with one route' => [
                [
                    'phplist/foo' => [
                        'phplist/phplist4-core' => [
                            'routes' => [
                                'application_homepage' => [
                                    'path' => '/',
                                    'defaults' => ['_controller' => 'PhpListApplicationBundle:Default:index'],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'phplist/foo.application_homepage' => [
                        'path' => '/',
                        'defaults' => ['_controller' => 'PhpListApplicationBundle:Default:index'],
                    ],
                ],
            ],
            'one module with two routes' => [
                [
                    'phplist/foo' => [
                        'phplist/phplist4-core' => [
                            'routes' => [
                                'homepage' => [
                                    'path' => '/',
                                    'defaults' => ['_controller' => 'PhpListApplicationBundle:Default:index'],
                                ],
                                'blog' => [
                                    'path' => '/blog',
                                    'defaults' => ['_controller' => 'PhpListApplicationBundle:Blog:index'],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'phplist/foo.homepage' => [
                        'path' => '/',
                        'defaults' => ['_controller' => 'PhpListApplicationBundle:Default:index'],
                    ],
                    'phplist/foo.blog' => [
                        'path' => '/blog',
                        'defaults' => ['_controller' => 'PhpListApplicationBundle:Blog:index'],
                    ],
                ],
            ],
            'two module with one route each' => [
                [
                    'phplist/foo' => [
                        'phplist/phplist4-core' => [
                            'routes' => [
                                'homepage' => [
                                    'path' => '/',
                                    'defaults' => ['_controller' => 'PhpListApplicationBundle:Default:index'],
                                ],
                            ],
                        ],
                    ],
                    'phplist/bar' => [
                        'phplist/phplist4-core' => [
                            'routes' => [
                                'blog' => [
                                    'path' => '/blog',
                                    'defaults' => ['_controller' => 'PhpListApplicationBundle:Blog:index'],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'phplist/foo.homepage' => [
                        'path' => '/',
                        'defaults' => ['_controller' => 'PhpListApplicationBundle:Default:index'],
                    ],
                    'phplist/bar.blog' => [
                        'path' => '/blog',
                        'defaults' => ['_controller' => 'PhpListApplicationBundle:Blog:index'],
                    ],
                ],
            ],
        ];

        $moduleSets = [];
        /** @var array[] $dataSet */
        foreach ($dataSets as $dataSetName => $dataSet) {
            /** @var string[][][] $extraSets */
            /** @var array[] $expectedRoutes */
            list($extraSets, $expectedRoutes) = $dataSet;

            $testCases = [];
            foreach ($extraSets as $packageName => $extraSet) {
                /** @var PackageInterface|ProphecySubjectInterface $packageProphecy */
                $packageProphecy = $this->prophesize(PackageInterface::class);
                $packageProphecy->getExtra()->willReturn($extraSet);
                $packageProphecy->getName()->willReturn($packageName);
                $testCases[] = $packageProphecy->reveal();
            }
            $moduleSets[$dataSetName] = [$testCases, $expectedRoutes];
        }

        return $moduleSets;
    }

    /**
     * @test
     * @param PackageInterface[] $modules
     * @param array[] $expectedRoutes
     * @dataProvider modulesWithRoutesDataProvider
     */
    public function findRoutesForModulesWithRoutesReturnsRoutes(array $modules, array $expectedRoutes)
    {
        $this->packageRepositoryProphecy->findModules()->willReturn($modules);

        $result = $this->subject->findRoutes();

        self::assertSame($expectedRoutes, $result);
    }

    /**
     * @test
     */
    public function createRouteConfigurationYamlForNoModulesReturnsCommentOnly()
    {
        $this->packageRepositoryProphecy->findModules()->willReturn([]);

        $result = $this->subject->createRouteConfigurationYaml();

        self::assertSame(self::YAML_COMMENT . "\n{  }", $result);
    }

    /**
     * @test
     * @param PackageInterface[][] $modules
     * @param array[] $routes
     * @dataProvider modulesWithRoutesDataProvider
     */
    public function createRouteConfigurationYamlReturnsYamlForRoutes(array $modules, array $routes)
    {
        $this->packageRepositoryProphecy->findModules()->willReturn($modules);

        $result = $this->subject->createRouteConfigurationYaml();

        self::assertSame(self::YAML_COMMENT . "\n" . Yaml::dump($routes), $result);
    }
}
