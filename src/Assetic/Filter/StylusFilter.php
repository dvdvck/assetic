<?php

/*
 * This file is part of the Assetic package, an OpenSky project.
 *
 * (c) 2010-2012 OpenSky Project Inc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Assetic\Filter;

use Assetic\Asset\AssetInterface;
use Assetic\Exception\FilterException;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Loads STYL files.
 *
 * @link http://learnboost.github.com/stylus/
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class StylusFilter implements FilterInterface
{
    private $stylusPath;
    private $nodePath;
    private $modulesPath;

    // Stylus options
    private $compress;
    private $useNib;
    private $imports;
    private $markContext;

    /**
     * Constructs filter.
     *
     * @param string $stylusPath      The path to the stylus binary
     * @param string $nodePath        The path to the node binary
     * @param array  $nodeModulesPath An array of node paths

     TODO
        Modificar la extension para agregar un tag para agregar los import
        Agregar un CompilerPass que inyecte los imports de los tags al filtro

        Buscar una manera de poder separar los import globales de los que 
        solo aplican para los style que estan siendo declarados en la etiqueta,
        funcion twig y/o formula config
     */
    public function __construct($stylusPath = '/usr/bin/stylus', $nodePath = '/usr/bin/node', array $nodeModulesPath = array())
    {
        $this->stylusPath = $stylusPath;
        $this->nodePath = $nodePath;
        $this->modulesPath = $nodeModulesPath;
        $this->imports=array();
    }

    /**
     * Enable output compression.
     *
     * @param   boolean     $compress
     */
    public function setCompress($compress)
    {
        $this->compress = $compress;
    }

    /**
     * Enable the use of Nib
     *
     * @param   boolean     $useNib
     */
    public function setUseNib($useNib)
    {
        $this->useNib = $useNib;
    }

    /**
     * Inject the context character mark
     *
     * @param   string     $markContext
     */
    public function setMarkContext($markContext)
    {
        $this->markContext = $markContext;
    }

    /**
     * Add import declared on config files
     *
     * @param   string     $markContext
     */
    public function addImport($import)
    {
        array_push($this->imports, $import);
    }

    /**
     * {@inheritdoc}
     */
    public function filterLoad(AssetInterface $asset)
    {
        $root = $asset->getSourceRoot();
        $path = $asset->getSourcePath();

        //scan the asset for tag context
        $context = strpos($asset->getContent(), $this->markContext);
        if($context!==false){
            if ($root && $path) {
                array_push($this->imports, $root.'/'.$path);
                return;
            }
        }

        $pb = new ProcessBuilder(array(
            $this->nodePath,
            $this->stylusPath,
        ));

        for ($i = count($this->modulesPath) - 1; $i >= 0; $i--) {
            $pb
                ->add('--include')
                ->add($this->modulesPath[$i])
            ;
        }

        if ($root && $path) {
            $pb
                ->add('--include')
                ->add(dirname($root.'/'.$path))
            ;
        }

        if ($this->compress) {
            $pb->add('--compress');
        }

        if ($this->useNib) {
            $pb
                ->add('--use')
                ->add('nib')
                ->add('--import')
                ->add('nib')
            ;
        }

        foreach($this->imports as $import){
            $pb
                ->add('--import')
                ->add($import)
            ;
        }

        // We need to override stdin as it's the only way to use stdout to fetch the results
        // (otherwise, stylus overwrites the input file if we try using a temporary file without the .styl extension)
        $pb->setInput($asset->getContent());
        $proc = $pb->getProcess();
        $code = $proc->run();

        if (0 < $code) {
            throw FilterException::fromProcess($proc)->setInput($asset->getContent());
        }

        $asset->setContent($proc->getOutput());
    }

    /**
     * {@inheritdoc}
     */
    public function filterDump(AssetInterface $asset)
    {
        //scan the asset for tag context
        // $context = strpos($asset->getContent(), $this->markContext);
        // if($context!==false){
        //     // $asset->setContent('');
        //     // return;
        // }

        $currentVal = $asset->getValues();

        foreach($currentVal as $var =>$val){
            $asset->setContent($var.'='.$val.$asset->getContent());
        }
    }
}
