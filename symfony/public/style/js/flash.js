function addFlash(type, message) {
  console.log(1);
  console.log(message);
  const flashbox = $("#flashbox");
        flashbox.append(
          $("<div class='alert alert-" + type + " alert-dismissible' role='alert'>" + 
            " <button type='button' class='close' data-dismiss='alert'><span aria-hidden='true'>&times;</span><span class='sr-only'>Close</span></button>" +
            " <span>" + message + "</span>" +
            "</div>")
        )
}