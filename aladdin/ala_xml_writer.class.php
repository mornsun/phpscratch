<?php
/***************************************************************************
 * 
 * Copyright (c) 2015 Chauncey. All Rights Reserved
 * 
 **************************************************************************/



/**
 * @file AlaSitemapWriter.class.php
 * @author Chauncey(https://github.com/mornsun/)
 *
 * @brief A script to help webmasters and data-owners to transform their structural data
 *       to Baidu search aladdin sitemap protocol (Box Computing Data Open Platform).
 *       It will organize data to an index file and multiple data file, such as sitemap_index.xml,
 *       sitemap_1.xml, sitemap_2.xml, ..., sitemap_n.xml
 *
 *       Utilize the test() function to have an insight.
 *
 **/

class AlaSitemapWriter
{
    protected $m_writer;
    protected $m_path;
    protected $m_filename;
    protected $m_sitemap_file;
    protected $m_cur_item = 0;
    protected $m_cur_sitemap_id = 0;
    protected $m_default_lastmod;
    protected $m_default_changefreq;
    protected $m_data;

    const DEFAULT_ENCODING = 'GB18030';
    const DEFAULT_PROTOCOL = '1.0';
    const ITEM_PER_SIZECHECK = 100;
    const DEFAULT_PRIORITY = 1;
    const SITEMAP_CHECK_SIZE = 9000000;
    const SEPERATOR = '_';
    const INDEX_SUFFIX = 'index';
    const EXT = '.xml' ;
    const DEFAULT_CHANGEFREQ = 'always';

    /**
     * * Constructor
     * *
     * * @param string $domain
     * */
    function __construct($path, $filename, $changefreq = self::DEFAULT_CHANGEFREQ)
    {
        $this->m_path = $path;
        $this->m_filename = $filename;
        $this->m_default_changefreq = $changefreq;
        $this->m_default_lastmod = date('Y-m-d', time());
    }

    /**
     * * Return XMLWriter object instance
     * *
     * * @return XMLWriter
     * */
    protected function get_writer()
    {
        return $this->m_writer;
    }

    /**
     * * Assign XMLWriter object instance
     * *
     * * @param XMLWriter $writer
     * */
    protected function set_writer(XMLWriter $writer)
    {
        $this->m_writer = $writer;
    }

    /**
     * * Return path of sitemaps
     * *
     * * @return string
     * */
    protected function get_path()
    {
        return $this->m_path;
    }

    /**
     * * Set paths of sitemaps
     * *
     * * @param string $path
     * */
    protected function set_path($path)
    {
        $this->m_path = $path;
    }

    /**
     * * Return filename of sitemap file
     * *
     * * @return string
     * */
    protected function get_filename()
    {
        return $this->m_filename;
    }

    /**
     * * Set filename of sitemap file
     * *
     * * @param string $filename
     * */
    protected function set_filename($filename)
    {
        $this->m_filename = $filename;
    }

    /**
     * * Return current item count
     * *
     * * @return int
     * */
    protected function get_cur_item()
    {
        return $this->m_cur_item;
    }

    /**
     * * Increase item counter
     * *
     * */
    protected function inc_cur_item()
    {
        ++$this->m_cur_item;
    }

    /**
     * * Return current sitemap file count
     * *
     * * @return int
     * */
    protected function get_cur_sitemap_id()
    {
        return $this->m_cur_sitemap_id;
    }

    /**
     * * Increase sitemap file count
     * *
     * */
    protected function inc_cur_sitemap()
    {
        ++$this->m_cur_sitemap_id;
    }

    /**
     * * Get current sitemap path including filename and ext
     * *
     * * @return string
     * */
    protected function get_cur_sitemap_file()
    {
        if (NULL == $this->m_sitemap_file) {
            $this->m_sitemap_file = $this->get_path() . $this->get_filename(). self::SEPERATOR . $this->get_cur_sitemap_id() . self::EXT;
        }
        return $this->m_sitemap_file;
    }

    /**
     * * Start sitemap XML document
     * *
     * */
    protected function start_sitemap()
    {
        $this->set_writer(new XMLWriter());
        $this->get_writer()->openURI($this->get_cur_sitemap_file());
        $this->get_writer()->startDocument(self::DEFAULT_PROTOCOL, self::DEFAULT_ENCODING);
        $this->get_writer()->setIndent(true);
        $this->get_writer()->startElement('urlset');
    }

    /**
     * * Finalize tags of sitemap XML document.
     * *
     * */
    protected function end_sitemap()
    {
        $this->get_writer()->endElement();
        $this->get_writer()->endDocument();
        $this->m_sitemap_file = NULL;
    }

    /**
     * * Prepare a display item
     * *
     * */
    public function prepare_disp_item($name, $attributes, $text)
    {
        $item[0] = $name;
        $item[1] = $attributes;
        $item[2] = $text;
        $this->m_data[0] = $item;
    }

    /**
     * * Add display items to XMLWriter
     * *
     * */
    protected function write_disp_items()
    {
        foreach ($this->m_data as $item) {
            if ($item[0] === NULL) 
                continue;
            $this->get_writer()->startElement($item[0]); //name
            foreach ($item[1] as $att_key => $att_val) {
                $this->get_writer()->writeAttribute($att_key, $att_val);
            }
            if ($item[2] !== NULL)
                $this->get_writer()->text($item[2]);
            $this->get_writer()->endElement(); //name
        }
    }

    /**
     * * Get size on Linux with a command
     * * It is said that on some OS it is quicker than filesize
     * *
     * * @return int
     * */
    public static function get_file_size($path)
    {
        $cmd = "du -b $path";
        exec($cmd,$output,$ret);
        $out = explode("\t", $output[0]);
        return intval($out[0]);
    }

    /**
     * * Check size of current sitemap file
     * *
     * * @return boolean
     * */
    protected function cur_sitemap_exceed()
    {
        $this->get_writer()->flush();
        clearstatcache();
        $fsize = filesize($this->get_cur_sitemap_file());
        //$fsize = self::get_file_size($this->get_cur_sitemap_file());
        if ($fsize > self::SITEMAP_CHECK_SIZE) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * * Adds an item to sitemap
     * *
     * * @param string $loc URL of the page. This value must be less than 2,048 characters.
     * * @param string $priority The priority of this URL relative to other URLs on your site. Valid values range from 0.0 to 1.0.
     * * @param string $changefreq How frequently the page is likely to change. Valid values are always, hourly, daily, weekly, monthly, yearly and never.
     * * @param string|int $lastmod The date of last modification of url. Unix timestamp or any English textual datetime description.
     * */
    public function add_url_item($loc, $priority = self::DEFAULT_PRIORITY, $changefreq = NULL, $lastmod = NULL)
    {
        if (($this->get_cur_item() % self::ITEM_PER_SIZECHECK) == 0) {
            if ($this->get_writer()) {
                if ($this->cur_sitemap_exceed()) {
                    $this->end_sitemap();
                    $this->inc_cur_sitemap();
                    $this->start_sitemap();
                }
            } else { // the 1st data file
                $this->inc_cur_sitemap();
                $this->start_sitemap();
            }
        }
        $this->inc_cur_item();
 
        $this->get_writer()->startElement('url');
        $this->get_writer()->writeElement('loc', $loc);
        $this->get_writer()->writeElement('priority', $priority);
        if ($changefreq === NULL)
            $changefreq = $this->m_default_changefreq;
        $this->get_writer()->writeElement('changefreq', $changefreq);
        if ($lastmod === NULL)
            $lastmod = $this->m_default_lastmod;
        $this->get_writer()->writeElement('lastmod', $lastmod);

        $this->get_writer()->startElement('data');
        $this->get_writer()->startElement('display');

        $this->write_disp_items();

        $this->get_writer()->endElement(); //display
        $this->get_writer()->endElement(); //data
        $this->get_writer()->endElement(); //url
    }

    /**
     * * Write sitemap index for generated sitemap files
     * *
     * * @param string $loc Accessible URL path of sitemaps
     * * @param string|int $lastmod The date of last modification of sitemap. Unix timestamp or any English textual datetime description.
     * */
    public function create_sitemap_index($loc, $lastmod = NULL) {
        $this->end_sitemap();
        $indexwriter = new XMLWriter();
        $indexwriter->openURI($this->get_path() . $this->get_filename() . self::SEPERATOR . self::INDEX_SUFFIX . self::EXT);
        $indexwriter->startDocument(self::DEFAULT_PROTOCOL, self::DEFAULT_ENCODING);
        $indexwriter->setIndent(true);
        $indexwriter->startElement('sitemapindex');
        for ($index = 1; $index <= $this->get_cur_sitemap_id(); ++$index) {
            $indexwriter->startElement('sitemap');
            $indexwriter->writeElement('loc', $loc . $this->get_filename() . self::SEPERATOR . $index . self::EXT);
            if ($lastmod === NULL)
                $lastmod = $this->m_default_lastmod;
            $indexwriter->writeElement('lastmod', $lastmod);
            $indexwriter->endElement();
        }
        $indexwriter->endElement();
        $indexwriter->endDocument();
    }

}

function test()
{
    $writer = new AlaSitemapWriter('./', 'sitemap');
    for($i=0; $i<100000; ++$i) {
        $writer->prepare_disp_item('hi', array('O' => 'y', 'M' => 'n', 'G' => 'y'), 'good');
        $writer->add_url_item('http://open.baidu.com/');
    }
    $writer->create_sitemap_index('http://open.baidu.com/');
}

test();
/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */
?>
