<?php
namespace codingninjas;

class AppExt
{
    static $instance = null;

    public function __construct()
    {
        add_action('init', array($this, 'onInit'));
        add_filter('enter_title_here', array($this, 'filterEnterTitle'));
        add_action('save_post_task', array($this, 'onSaveTask'));
        add_filter('document_title_parts', array($this, 'filterTitle'));
        add_shortcode('cn_dashboard', array($this, 'shortcodeDashboard'));
        add_filter('cn_tasks_thead_cols', array($this, 'filterTasksHeadCols'));
        add_filter('cn_tasks_tbody_row_cols', array($this, 'filterTasksRowCols'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'onEnqueueScripts'), 22);
        add_filter('cn_menu', array($this, 'filterMenu'), 10, 2);
        add_action('cn_after_tasks_table', array($this, 'onAfterTasksTable'));
        add_action('wp_ajax_add_task', array($this, 'onAddTask'));
        add_action('wp_ajax_nopriv_add_task', array($this, 'onAddTaskNopriv'));
    }

    static function run()
    {
        // singleton pattern
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    function onAddTask()
    {
        check_ajax_referer();
        $title = isset($_POST['task_title']) ? $_POST['task_title'] : '';
        $freelancerID = $_POST['freelancer'] ? $_POST['freelancer'] : '';
        if ($title != '') {
            $task = [
                'post_title' => $title,
                'post_type' => 'task',
                'post_status' => 'publish',
            ];
            $pid = wp_insert_post($task);
            if($pid) {
                if($freelancerID)
                {
                    update_post_meta($pid, 'freelancer', $freelancerID);
                }
                die(json_encode(['success'=> 1, 'message' => __('Success!')]));
            }
            else {
                die(json_encode(['success'=> 0, 'message'=> __('Error saving Task')]));
            }

        } else
        {
            die(json_encode(['success'=> 0, 'message'=> __('Please set Task title')]));
        }
    }

    function onAddTaskNopriv()
    {
        die(json_encode(['success'=> 0, 'message'=> __('Please login to add Tasks')]));
    }

    function filterMenu($menu, $route)
    {
        if ($route == 'tasks') {
            $menu['/add-task'] = [
                'title' => 'Add New Task',
                'icon' => 'fa-plus-circle',
                'url' => '#add-task'
            ];
        }
        return $menu;
    }

    function filterTasksHeadCols($cols)
    {
        array_splice($cols, 2, 0, __('Freelancer', 'cn'));
        return $cols;
    }

    function onEnqueueScripts()
    {
        if (App::$route == 'tasks') {
            wp_enqueue_style(
                'datatables',
                plugin_dir_url(__FILE__) . 'assets/jquery.dataTables.min.css'
            );
            wp_enqueue_script(
                'datatables',
                plugin_dir_url(__FILE__) . 'assets/jquery.dataTables.min.js',
                ['jquery']
            );
        }
        wp_enqueue_script(
            'coding-ninjas-ext',
            plugin_dir_url(__FILE__) . 'assets/coding-ninjas-ext.js',
            ['datatables']
        );
        wp_localize_script(
            'coding-ninjas-ext',
            'cn',
            array('ajaxurl' => admin_url('admin-ajax.php')));

    }

    function filterTasksRowCols($cols, $task)
    {
        $freelancerId = get_post_meta(ltrim($task->id(), '#'), 'freelancer', true);

        array_splice($cols, 2, 0, [$freelancerId ? get_the_title($freelancerId) : '']);
        return $cols;
    }

    function shortcodeDashboard()
    {
        $freelancerCount = wp_count_posts('freelancer')->publish;
        $taskCount = wp_count_posts('task')->publish;

        ob_start();
        ?>
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <div class="row">
                            <div class="col-xs-3">
                                <i class="fa fa-users fa-5x"></i>
                            </div>
                            <div class="col-xs-9 text-right">
                                <div class="huge"><?= $freelancerCount ?></div>
                                <div><?= _n('Freelancer', 'Freelancers', $freelancerCount, 'cn') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="<?= site_url() ?>/tasks">
                    <div class="panel panel-green">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-xs-3">
                                    <i class="fa fa-tasks fa-5x"></i>
                                </div>
                                <div class="col-xs-9 text-right">
                                    <div class="huge"><?= $taskCount ?></div>
                                    <div><?= _n('Task', 'Tasks', $taskCount, 'cn') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    function onAfterTasksTable($page)
    {
        ?>
        <!-- Modal -->
        <div class="modal fade" id="addTask" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="myModalLabel">Add New Task</h4>
                    </div>
                    <div class="modal-body">
                        <form id="add_task_modal">
                            <input name="action" type="hidden" value="add_task"/>
                            <div class="row form-group">
                                <div class="col-md-4 text-right"><label for="task_title">Task Title</label></div>
                                <div class="col-md-5"><input class="form-control" type="text" name="task_title"
                                                             id="task_title"/></div>
                            </div>
                            <div class="row form-group">
                                <div class="col-md-4 text-right"><label for="freelancer">Freelancer</label></div>
                                <div class="col-md-5">
                                    <select name='freelancer' id='freelancer'>
                                        <option value=""><?= __('Select Freelancer', 'cn') ?></option>
                                        <?php $freelancers = get_posts(array('post_type' => 'freelancer', 'numberposts' => 0, 'post_status' => 'publish'));
                                        foreach ($freelancers as $freelancer):
                                            $tasksCount = count(get_posts(array(
                                                'post_type' => 'task',
                                                'post_status' => 'publish',
                                                'numberposts' => 0,
                                                'meta_query' => array(
                                                    'key' => 'freelancer',
                                                    'value' => $freelancer->ID
                                                )
                                            )));
                                            ?>
                                            <option <?= $tasksCount > 2 ? 'disabled' : '' ?>
                                                value="<?php echo esc_attr($freelancer->ID); ?>"><?php echo esc_html($freelancer->post_title) . " ($tasksCount)"; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row form-group">
                                <div class="col-md-4"></div>
                                <div class="col-md-5">
                                    <button type="submit" class="btn btn-primary">Add</button>
                                </div>
                            </div>
                            <?php wp_nonce_field(); ?>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>

                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    function filterTitle($in)
    {
        switch (App::$route) {
            case 'dashboard':
                $in['title'] = "Dashboard";
                break;
            case 'tasks':
                $in['title'] = "Tasks";
                break;

        }

        return $in;
    }

    function filterEnterTitle($in)
    {
        if ('freelancer' === get_post_type()) {
            return __('Enter Freelancer name here', 'cn');
        }

        return $in;
    }

    static function onActivate()
    {
        if (!class_exists('codingninjas\App')) {
            deactivate_plugins(plugin_basename(__FILE__));
            die('<p style="font-family: -apple-system,BlinkMacSystemFont,Roboto,Oxygen-Sans,Ubuntu,Cantarell,sans-serif;font-size: 13px;">This plugin depends on Coding Ninjas plugin. Please activate Coding Ninjas plugin first.</p>');
        }
    }

    function onInit()
    {
        $this->CreatePostType();
        add_action('do_meta_boxes', array(&$this, 'onChangeMetaboxes'));
        add_filter('admin_post_thumbnail_html', array(&$this, 'filterPostThumbnail'));
    }

    function onChangeMetaboxes()
    {
        if ('freelancer' === get_post_type()) {
            remove_meta_box('postimagediv', 'freelancer', 'side');
            add_meta_box('postimagediv', __('Avatar', 'cn'), 'post_thumbnail_meta_box', 'freelancer', 'side');
        } elseif ('task' === get_post_type()) {
            add_meta_box('freelancerdiv', __('Freelancer', 'cn'), array(&self::$instance, 'task_freelancer_meta_box'), 'task', 'side');
        }
    }

    function task_freelancer_meta_box($post)
    {
        $currentFreelancer = get_post_meta($post->ID, 'freelancer', true);
        ?>
        <p>
            <label for="task_meta_freelancer">Freelancer: </label>
            <select name='task_meta_freelancer' id='task_meta_freelancer'>
                <option value=""><?= __('Select Freelancer', 'cn') ?></option>
                <?php $freelancers = get_posts(array('post_type' => 'freelancer', 'numberposts' => 0));
                foreach ($freelancers as $freelancer): ?>
                    <option value="<?php echo esc_attr($freelancer->ID); ?>"
                        <?= $freelancer->ID == $currentFreelancer ? 'selected' : '' ?>><?php echo esc_html($freelancer->post_title); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    function onSaveTask($postID)
    {
        if (isset($_POST['task_meta_freelancer'])) {
            $freelancerID = $_POST['task_meta_freelancer'];

            // validate ID. Numerics are safe to put into meta.
            if (is_numeric($freelancerID)) {
                update_post_meta($postID, 'freelancer', $freelancerID);
            } else {
                delete_post_meta($postID, 'freelancer');
            }
        }
    }

    function filterPostThumbnail($in)
    {
        if ('freelancer' === get_post_type()) {
            $in = str_replace('Set featured image', __('Set Avatar', 'cn'), $in);
            $in = str_replace('Remove featured image', __('Remove Avatar', 'cn'), $in);
        }
        return $in;
    }

    function CreatePostType()
    {
        register_post_type('freelancer',
            array(
                'labels' => array(
                    'name' => __('Freelancers', 'cn'),
                    'singular_name' => __('Freelancer', 'cn'),
                    'menu_name' => __('Freelancers', 'cn'),
                    'name_admin_bar' => __('Freelancer', 'cn'),
                    'add_new' => __('Add New', 'cn'),
                    'add_new_item' => __('Add New Freelancer', 'cn'),
                    'new_item' => __('New Freelancer', 'cn'),
                    'edit_item' => __('Edit Freelancer', 'cn'),
                    'view_item' => __('View Freelancer', 'cn'),
                    'all_items' => __('All Freelancers', 'cn'),
                    'search_items' => __('Search Freelancers', 'cn'),
                    'parent_item_colon' => __('Parent Freelancers:', 'cn'),
                    'not_found' => __('No freelancers found.', 'cn'),
                    'not_found_in_trash' => __('No freelancers found in Trash.', 'cn')
                ),
                'public' => true,
                'has_archive' => true,
                'publicly_queryable' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'query_var' => true,
                'rewrite' => array('slug' => 'freelancer'),
                'menu_icon' => 'dashicons-businessman',
                'capability_type' => 'post',
                'has_archive' => true,
                'hierarchical' => false,
                'menu_position' => null,
                'supports' => array('title', 'editor', 'thumbnail')
            )
        );
    }
}