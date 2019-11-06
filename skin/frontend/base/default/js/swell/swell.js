jQuery(document).ready(function () {
    jQuery.ajax({
        url: "/swell/session/snippet_response",
        type: "POST",
        success: function(response){
            jQuery(".footer-container").after(response);
        }
    });
});
