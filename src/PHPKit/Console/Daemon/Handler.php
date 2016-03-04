<?php
/**
 * @package PHPKit.
 * @author: mawenpei
 * @date: 2016/3/2
 * @time: 11:30
 */

namespace PHPKit\Console\Daemon;

use PHPKit\Console\Traits\HandlerTrait;
use PHPKit\Console\Config\Loader;

class Handler
{
    use HandlerTrait;

    /**
     * ע���ź�
     */
    public static function registerSignal()
    {
        //��ֹ����
        \swoole_process::signal(SIGTERM, function ($signo) {
            self::exit2p( 'master process [' . self::$process_name . '] exit');
        });
        //�����ź�,���ӽ���ֹͣ���˳�ʱ֪ͨ������
        \swoole_process::signal(SIGCHLD, function ($signo) {
            //�������ȴ�
            while ($ret = \swoole_process::wait(false)) {
                $pid = $ret['pid'];
                if (isset(self::$worker_list[$pid])) {
                    $worker = self::$worker_list[$pid];
                    $worker['process']->close();
                    //�������ӽ���
                    self::createWorkerProcess($worker['className'],$worker['number'],$worker['options']);
                    defined('PHPKIT_RUN_DEBUG') && syslog(LOG_INFO,'child process restart:' . $pid);
                }
            };
        });
        //��ֹ����,�û������ź�1
        \swoole_process::signal(SIGUSR1, function ($signo) {
            //TODO something
        });

        defined('PHPKIT_RUN_DEBUG') && syslog(LOG_INFO,'register signal success');
    }

    public static function startUp()
    {
        self::registerTimer();
        self::loadWorkers();
    }

    public static function registerTimer()
    {
        \swoole_timer_tick(5000,function($id){
            defined('PHPKIT_RUN_DEBUG') && syslog(LOG_INFO,'reload config success');
        });
    }

    public static function loadWorkers()
    {
        $workers = Loader::getInstance()->config();

        defined('PHPKIT_RUN_DEBUG') && syslog(LOG_INFO,'load config success');
        foreach($workers as $worker){
            for($i=1;$i<=$worker['processNum'];$i++){
                self::createWorkerProcess($worker['className'],$i,$worker);
            }
        }
    }

    public static function createWorkerProcess($className,$number,$options)
    {
        $reflector = new \ReflectionClass($className);
        if(!$reflector->implementsInterface("PHPKit\\Console\\Workers\\IWorker")){
            defined('PHPKIT_RUN_DEBUG') && syslog(LOG_ERR,'class [' . $className . '] is error');
            self::stop();
        }else{
            $process = new \swoole_process(function($worker)use($className,$number,$options){
                $worker->name(self::$child_process_name . str_replace('\\','_',$className) . '_' . $number);
                $handler = new $className($options);
                $handler->tick($worker);
            });
            $pid = $process->start();
            self::$worker_list[$pid] = [
                'className'=>$className,
                'number'=>$number,
                'process'=>$process,
                'options'=>$options
            ];
            defined('PHPKIT_RUN_DEBUG') && syslog(LOG_INFO,'create child process success:' . $pid);
        }
    }
}