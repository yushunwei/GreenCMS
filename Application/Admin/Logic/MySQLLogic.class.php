<?php
/**
 * Created by Green Studio.
 * File: MySQLLogic.class.php
 * User: TianShuo
 * Date: 14-1-27
 * Time: 下午8:33
 */

namespace Admin\Logic;

use Think\Model;
use Common\Util\Dir;

class MySQLLogic extends Model
{
    static public $sqlFilesSize = 0;

    /**
    +----------------------------------------------------------
     * 功能：获取数据库中所有表名
    +----------------------------------------------------------
     * @return array
    +----------------------------------------------------------
     */
    public function getAllTableName()
    {
        $tabs = M()->query('SHOW TABLE STATUS');
        $arr = array();
        foreach ($tabs as $tab) {
            $arr[] = $tab['Name'];
        }
        unset($tabs);
        return $arr;
    }

    /**
    +----------------------------------------------------------
     * 功能：读取数据库表结构信息
    +----------------------------------------------------------
     * @param $table_list
     * @return string
    +----------------------------------------------------------
     */
    public function bakupTable($table_list)
    {
        //M()->query("SET OPTION SQL_QUOTE_SHOW_CREATE = 1"); //1，表示表名和字段名会用``包着的,0 则不用``
        $outPut = '';
        if (!is_array($table_list) || empty($table_list)) {
            return false;
        }
        foreach ($table_list as $table) {
            $outPut .= "# 数据库表：{$table} 结构信息\n";
            $outPut .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $tmp = M()->query("SHOW CREATE TABLE {$table}");
            $outPut .= $tmp[0]['Create Table'] . " ;\n\n";
        }
        return $outPut;
    }

    /**
    +----------------------------------------------------------
     * 功能：读取已经备份SQL文件列表，并按备份时间倒序，名称升序排列
    +----------------------------------------------------------
     * @return array
    +----------------------------------------------------------
     */
    public function getSqlFilesList()
    {
        $list = array();
        $size = 0;
        $handle = opendir(DB_Backup_PATH);

        while ($file = readdir($handle)) {
            if (preg_match('#\.sql$#i', $file)) {
                $fp = fopen(DB_Backup_PATH . "/$file", 'rb');
                $bakinfo = fread($fp, 2000);
                fclose($fp);
                $detail = explode("\n", $bakinfo);
                $bk = array();
                $bk['name'] = $file;
                $bk['url'] = substr($detail[2], 7);
                $bk['type'] = substr($detail[3], 8);
                $bk['description'] = substr($detail[4], 14);
                $bk['time'] = substr($detail[5], 8);
                $_size = filesize(DB_Backup_PATH . "/$file");
                $bk['size'] = byteFormat($_size);
                $size += $_size;
                $bk['pre'] = substr($file, 0, strrpos($file, '_'));
                $bk['num'] = substr($file, strrpos($file, '_') + 1, strrpos($file, '.') - 1 - strrpos($file, '_'));
                $mtime = filemtime(DB_Backup_PATH . "/$file");
                $list[$mtime][$file] = $bk;
            }
        }
        closedir($handle);
        krsort($list); //按备份时间倒序排列
        $newArr = array();
        foreach ($list as $k => $array) {
            ksort($array); //按备份文件名称顺序排列
            foreach ($array as $arr) {
                $newArr[] = $arr;
            }
        }
        unset($list);
        return array("list" => $newArr, "size" => byteFormat($size));
    }

    public function getZipFilesList()
    {
        $list = array();
        $size = 0;
        $handle = opendir(DB_Backup_PATH . "Zip/");

        while ($file = readdir($handle)) {
            if ($file != "." && $file != "..") {
                $tem = array();
                $tem['file'] = $file; //  checkCharset($file);
                $_size = filesize(DB_Backup_PATH . "Zip/$file");
                $tem['size'] = byteFormat($_size);
                $tem['time'] = date("Y-m-d H:i:s", filectime(DB_Backup_PATH . "Zip/$file"));
                $size += $_size;
                $list[] = $tem;
            }
        }
        return array("list" => $list, "size" => byteFormat($size));
    }

    /**
    +----------------------------------------------------------
     * 功能：生成zip压缩文件，存放都 WEB_CACHE_PATH 中
    +----------------------------------------------------------
     * @param $files        array   需要压缩的文件
     * @param $filename     string  压缩后的zip文件名  包括zip后缀
     * @param $path         string  文件所在目录
     * @param $outDir       string  输出目录
    +----------------------------------------------------------
     * @return array
    +----------------------------------------------------------
     */
    public function zip($files, $filename, $outDir = WEB_CACHE_PATH, $path = DB_Backup_PATH)
    {
        $zip = new ZipArchive;

        $dir = new Dir();
        $dir->makeDir($outDir);


        $res = $zip->open($outDir . "\\" . $filename, ZipArchive::CREATE);

        if ($res == TRUE) {
            foreach ($files as $file) {
                if ($t = $zip->addFile($path . $file, str_replace('/', '', $file))) {
                    //TODO zip压缩成功
                    Log::write('zip压缩成功');
                    $t = $zip->addFile($path . $file, str_replace('/', '', $file));
                }
            }
            $zip->close();
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
    +----------------------------------------------------------
     * 功能：解压缩zip文件，存放都 DB_Backup_PATH 中
    +----------------------------------------------------------
     * @param $file         string   需要压缩的文件
     * @param $outDir       string   解压文件存放目录
    +----------------------------------------------------------
     * @return array
    +----------------------------------------------------------
     */
    function unzip($file, $outDir = DB_Backup_PATH)
    {
        $zip = new ZipArchive();
        if ($zip->open(DB_Backup_PATH . "Zip/" . $file) !== TRUE)
            return FALSE;
        $zip->extractTo($outDir);
        $zip->close();
        return TRUE;
    }


}