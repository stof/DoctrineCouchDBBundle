<?php

/*
 * Doctrine CouchDB Bundle
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */

namespace Doctrine\Bundle\CouchDBBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Doctrine\Bundle\CouchDBBundle\DependencyInjection\Compiler\RegisterEventListenersAndSubscribersPass;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\DoctrineValidationPass;

class DoctrineCouchDBBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterEventListenersAndSubscribersPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new DoctrineValidationPass('couchdb'));
    }

    public function boot()
    {
        // force Doctrine annotations to be loaded
        // should be removed when a better solution is found in Doctrine
        class_exists('Doctrine\ODM\CouchDB\Mapping\Driver\AnnotationDriver');
        
        // Register an autoloader for proxies to avoid issues when unserializing them
        // when the ORM is used.
        if ($this->container->hasParameter('doctrine_couchdb.odm.proxy_namespace')) {
            $namespace = $this->container->getParameter('doctrine_couchdb.odm.proxy_namespace');
            $dir = $this->container->getParameter('doctrine_couchdb.odm.proxy_dir');
            $container = $this->container;

            spl_autoload_register(function($class) use ($namespace, $dir, $container) {
                if (0 === strpos($class, $namespace)) {
                    $className = substr($class, strlen($namespace) +1);
                    $file = $dir.DIRECTORY_SEPARATOR.$className.'.php';

                    if (!is_file($file) && $container->getParameter('kernel.debug')) {
                        $originalClassName = substr($className, 0, -5);
                        $registry = $container->get('doctrine_couchdb');

                        // Tries to auto-generate the proxy file
                        foreach ($registry->getObjectManagers() as $manager) {

                            if ($manager->getConfiguration()->getAutoGenerateProxyClasses()) {
                                $classes = $manager->getMetadataFactory()->getAllMetadata();

                                foreach ($classes as $class) {
                                    $name = str_replace('\\', '', $class->name);

                                    if ($name == $originalClassName) {
                                        $manager->getProxyFactory()->generateProxyClasses(array($class));
                                    }
                                }
                            }
                        }

                        clearstatcache($file);

                        if (!is_file($file)) {
                            throw new \RuntimeException(sprintf('The proxy file "%s" does not exist. If you still have objects serialized in the session, you need to clear the session manually.', $file));
                        }
                    }

                    require $file;
                }
            });
        }
    }
}