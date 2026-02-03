(function($) {
    'use strict';

    function reorderCompanySegmentsField() {
        if (!$('form[name="lead_field_import"]').length) return;

        var $segments = $('#lead_field_import_company_segments').closest('.col-xs-4, .col-sm-4');
        var $owner = $('#lead_field_import_owner').closest('.col-xs-4, .col-sm-4');

        if ($segments.length && $owner.length) {
            $owner.after($segments);
        }
    }

    $(function() {
        reorderCompanySegmentsField();
    });
    $(document).ajaxComplete(function(event, xhr, settings) {
        reorderCompanySegmentsField();
    });
})(mQuery);
