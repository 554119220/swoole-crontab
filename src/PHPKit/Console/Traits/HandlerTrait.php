<?php
/**
 * @package PHPKit.
 * @author: mawenpei
 * @date: 2016/3/3
 * @time: 16:08
 */

namespace PHPKit\Console\Traits;

trait HandlerTrait
{
    protected static $options = "hdrp:s:l:c:";

    protected static $longopts = ["help","daemon","reload","pid:","log:","config:"];

    /**
     * @var bool
     */
    public static $daemon = false;

    /**
     * @var pid ������ID
     */
    public static $pid;

    /**
     * @var pid_file ������pidĿ¼
     */
    public static $pid_file;

    /**
     * @var log_file ��־Ŀ¼
     */
    public static $log_file;

    /**
     * @var process_name ����������
     */
    public static $process_name;

    public static $child_process_name;

    /**
     * @var array worker_list �ӽ����б�
     */
    public static $worker_list = [];

    private static $help = <<<EOF
    Usage:/path/to/php console.config.php [options] -- [args...]

    -h [--help] show help
    -p [--pid] pid file dir
    -s start start service
    -s stop stop service
    -s restart restart service
    -s reload reload config
    -l [--log] log file dir


EOF;



    /**
     * ��������
     */
    public static function start()
    {
        if(file_exists(self::$pid_file)){
            syslog(LOG_INFO,'pid file ['.self::$pid_file.'] is exists');
            return;
        }
        //��̨����
        \swoole_process::daemon();
        //���ý�������
        \swoole_set_process_name(self::$process_name);

        self::get_pid();
        self::write_pid();

        self::registerSignal();

        self::startUp();

        defined('PHPKIT_RUN_DEBUG') && syslog(LOG_INFO,self::$process_name . ' start success');
    }

    /**
     * ֹͣ����
     */
    public static function stop()
    {
        $pid = file_get_contents(self::$pid_file);
        if($pid){
            if(\swoole_process::kill($pid,0)){
                \swoole_process::kill($pid,SIGTERM);
            }else{
                @unlink(self::$pid_file);
            }
            defined('PHPKIT_RUN_DEBUG') && syslog(LOG_INFO,self::$process_name . ' exit success');
        }
    }

    /**
     * ��������
     */
    public static function restart()
    {
        self::stop();
        sleep(1);
        self::start();
    }

    /*
     * ���¼��������ļ�
     */
    public static function reload()
    {

    }

    /**
     * �˳�������
     * @var message
     */
    public static function exit2p($message)
    {
        @unlink(self::$pid_file);
        syslog(LOG_INFO,$message);
        exit();
    }

    public static function run($process_name=null)
    {
        if($process_name){
            self::$process_name = $process_name;
            self::$child_process_name = $process_name . '_child_worker';
        }else{
            self::$process_name = 'swoole_' . CURRENT_RUN_MODE . '_master_worker';
            self::$child_process_name = 'swoole_' . CURRENT_RUN_MODE . '_child_worker';
        }

        self::parse_params();

    }


    /**
     * ��ʼ�������в���
     */
    public static function parse_params()
    {
        $opt = getopt(self::$options,self::$longopts);
        self::parse_params_h($opt);
        self::parse_params_p($opt);
        self::parse_params_l($opt);
        self::parse_params_c($opt);
        self::parse_params_r($opt);
        self::parse_params_s($opt);
    }

    /**
     * ����help/h����
     * @param $opt
     */
    public static function parse_params_h($opt)
    {
        if(empty($opt) || isset($opt['h']) || isset($opt['help'])){
            die(self::$help);
        }
    }

    public static function parse_params_r($opt)
    {
        if(isset($opt['r']) || isset($opt['reload'])){
            self::reload();
        }
        return false;
    }

    /**
     * ����pid�ļ�Ŀ¼����
     * @param $opt
     */
    public static function parse_params_p($opt)
    {
        if(isset($opt['p']) && $opt['p']){
            self::$pid_file = rtrim($opt['p'],'/') . '/' . self::$process_name . '.pid';
        }
        if(isset($opt['pid']) && $opt['pid']){
            self::$pid_file = rtrim($opt['pid'],'/') . '/' . self::$process_name . '.pid';
        }
        if(empty(self::$pid_file)){
            self::$pid_file = '/var/run/' . self::$process_name . '.pid';
        }
    }

    public static function parse_params_l($opt)
    {
        if(isset($opt['l']) && $opt['l']){

        }
        if(isset($opt['log']) && $opt['log']){

        }
        if(empty(self::$log_file)){

        }
    }

    public static function parse_params_c($opt)
    {
        if(isset($opt['c']) && $opt['c']){

        }
        if(isset($opt['config']) && $opt['config']){

        }
    }

    public static function parse_params_s($opt)
    {
        if(isset($opt['s']) && in_array($opt['s'],['start','stop','restart','reload'])){
            switch($opt['s']){
                case 'start':
                    self::start();
                    break;
                case 'stop':
                    self::stop();
                    break;
                case 'restart':
                    self::restart();
                    break;
                case 'reload':
                    self::reload();
                    break;
            }
        }
    }

    /**
     * ��ȡ������pid
     */
    protected static function get_pid()
    {
        self::$pid = posix_getpid();
    }

    /**
     * ����������pid
     */
    protected static function write_pid()
    {
        file_put_contents(self::$pid_file,self::$pid);
    }
}