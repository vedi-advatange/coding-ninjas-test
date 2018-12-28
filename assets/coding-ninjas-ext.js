jQuery(document).ready( function ($) {
    $('#tasks_table').addClass('display nowrap dataTable').DataTable();
    $('a[href="#add-task"]').attr('data-toggle', 'modal').attr('data-target', "#addTask");
    $('#add_task_modal').submit(
        function () {
            var that = this;
            $.post(
                cn.ajaxurl,
                $(this).serialize(),
                function (data) {
                    alert(data.message)
                    if(data.success) {
                        location.reload();
                    }
                },
                'json'
            );
            return false;
        }
    )
} );