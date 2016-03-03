<?php
/**
 * @package PHPKit.
 * @author: mawenpei
 * @date: 2016/3/3
 * @time: 16:15
 */

namespace PHPKit\Console\Crontab;

use PHPKit\Console\Traits\HandlerTrait;
use PHPKit\Console\Config\Loader;

class Handler
{
    use HandlerTrait;

    protected static $unique_task_list;

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
                    $task = self::$worker_list[$pid];
                    $id = $task['id'];
                    $task['process']->close();
                    unset(self::$worker_list[$pid]);
                    if (isset(self::$unique_task_list[$id]) && self::$unique_task_list[$id] > 0) {
                        self::$unique_task_list[$id]--;
                    }
                    defined('PHPKIT_RUN_DEBUG') && syslog(LOG_INFO,'child process exit:' . $pid);
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
        $run = true;
        while($run){
            $s = date('s');
            if($s == 0){
                self::loadConfig();
                self::registerTimer();
                $run = false;
            }else{
                syslog(LOG_INFO,'starting count down ' . (60-$s) . ' second' );
                sleep(1);
            }
        }
    }

    public static function registerTimer()
    {
        \swoole_timer_tick(60000,function($id){
            self::loadConfig();
            defined('PHPKIT_RUN_DEBUG') && syslog(LOG_INFO,'reload config success');
        });

        \swoole_timer_tick(1000,function($id){
            self::loadTask($id);
        });
    }

    public static function loadConfig()
    {
        $time = time();
        $config = Loader::config();
        foreach($config as $id=>$task){
            $ret = ParseCrontab::parse($task["rule"], $time);
            //defined('PHPKIT_RUN_DEBUG') && syslog(LOG_DEBUG,var_export($ret,true));
            if ($ret === false) {
                syslog(LOG_ERR,ParseCrontab::$error);
            } elseif (!empty($ret)) {
                TickTable::set_task($ret, array_merge($task, array("id" => $id)));
            }
        }

    }

    public static function loadTask($timer_id)
    {
        $tasks = TickTable::get_task();
        if(empty($tasks)) return false;
        //defined('PHPKIT_RUN_DEBUG') && syslog(LOG_DEBUG,var_export($tasks,true));
        foreach($tasks as $task){
            if(isset($task['unique']) && $task['unique']){
                if (isset(self::$unique_task_list[$task["id"]]) && (self::$unique_task_list[$task["id"]] >= $task["unique"])) {
                    continue;
                }
                self::$unique_task_list[$task["id"]] = isset(self::$unique_task_list[$task["id"]]) ? (self::$unique_task_list[$task["id"]] + 1) : 0;
            }
            self::createTaskProcess($task['id'],$task);
        }
    }

    public static function createTaskProcess($id,$task)
    {
        $className = $task['execute'];
        $reflector = new \ReflectionClass($className);
        if(!$reflector->implementsInterface("PHPKit\\Console\\Tasks\\ITask")){
            defined('PHPKIT_RUN_DEBUG') && syslog(LOG_ERR,'class [' . $className . '] is error');
            self::stop();
        }else{
            $process = new \swoole_process(function($worker)use($className,$task){
                $worker->name(self::$child_process_name . str_replace('\\','_',$className));
                $handler = new $className();
                $handler->doing($worker,$task['args']);
                $worker->exit(1);
            });
            $pid = $process->start();
            self::$worker_list[$pid] = [
                'className'=>$className,
                'id'=>$id,
                'process'=>$process,
                'task'=>$task
            ];
            defined('PHPKIT_RUN_DEBUG') && syslog(LOG_INFO,'create child process success:' . $pid);
        }
    }
}