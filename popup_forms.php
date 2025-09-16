<?php
include 'config.php';

// Fetch all vehicles from the database
$vehicles = [];
$sql = "SELECT id, model, number_plate FROM vehicle"; // Adjust table/columns
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="assets/dashboard.css">
    <link rel="icon" type="image/png" href="assets/vision.ico">
</head>
<body>

<!-- Fuel Popup -->
<div class="popup" id="fuelPopup">
  <div class="popup-content">
    <span class="close-btn" onclick="closePopup('fuelPopup')">&times;</span>
    <h2>Add Fuel Expense</h2>
    <form action="add_fuel.php" method="POST">
      <label>Date:</label>
      <input type="text" name="date" id="fuelDate" placeholder="dd-mm-yyyy" required class="datemandatory">

      <label>Division:</label>
      <select name="division" required>
        <option value="">-- Select Division --</option>
        <option value="Sales">Sales</option>
        <option value="Project">Project</option>
        <option value="Service">Service</option>
        <option value="Installation">Installation</option>
      </select>

      <label>Region:</label>
      <select name="region" required>
        <option value="">-- Select Region --</option>
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" required>
        <option value="">-- Select Company --</option>
        <option value="Redtag">Redtag</option>
        <option value="Landmark">Landmark</option>
        <option value="Apparel">Apparel</option>
        <option value="Other">Other</option>
      </select>

      <label>Store:</label>
      <input type="text" name="store" required>

      <label>Location:</label>
      <input type="text" name="location" required>

      <label>Description:</label>
      <textarea name="description" required></textarea>

      <label>Amount:</label>
      <input type="number" step="0.01" name="amount" required>

      <label>Bill:</label>
      <select name="bill" required>
        <option value="">--Bill--</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>

      <button type="submit" class="btn">Save Fuel</button>
    </form>
  </div>
</div>

<!-- Food Popup -->
<div class="popup" id="foodPopup">
  <div class="popup-content">
    <span class="close-btn" onclick="closePopup('foodPopup')">&times;</span>
    <h2>Add Food Expense</h2>
    <form action="add_food.php" method="POST">
      <label>Date:</label>
      <input type="text" name="date" id="foodDate" placeholder="dd-mm-yyyy" class="datemandatory" required>

      <label>Division:</label>
      <select name="division" required>
        <option value="">-- Select Division --</option>
        <option value="Sales">Sales</option>
        <option value="Project">Project</option>
        <option value="Service">Service</option>
        <option value="Installation">Installation</option>
      </select>

      <label>Region:</label>
      <select name="region" required>
        <option value="">-- Select Region --</option>
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" required>
        <option value="">-- Select Company --</option>
        <option value="Redtag">Redtag</option>
        <option value="Landmark">Landmark</option>
        <option value="Apparel">Apparel</option>
        <option value="Other">Other</option>
      </select>

      <label>Store:</label>
      <input type="text" name="store" required>

      <label>Location:</label>
      <input type="text" name="location" required>

      <label>Number of People:</label>
      <input type="number" name="people" required>

      <label>Description:</label>
      <textarea name="description" required></textarea>

      <label>Amount:</label>
      <input type="number" step="0.01" name="amount" required>

      <button type="submit" class="btn">Save Food</button>
    </form>
  </div>
</div>

<!-- Room Popup -->
<div class="popup" id="roomPopup">
  <div class="popup-content">
    <span class="close-btn" onclick="closePopup('roomPopup')">&times;</span>
    <h2>Add Room Expense</h2>
    <form action="add_room_expense.php" method="POST">
      <label>Date:</label>
      <input type="text" name="date" id="roomDate" placeholder="dd-mm-yyyy" class="datemandatory" required>

      <label>Division:</label>
      <select name="division" required>
        <option value="">-- Select Division --</option>
        <option value="Sales">Sales</option>
        <option value="Project">Project</option>
        <option value="Service">Service</option>
        <option value="Installation">Installation</option>
      </select>

      <label>Region:</label>
      <select name="region" required>
        <option value="">-- Select Region --</option>
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" required>
        <option value="">-- Select Company --</option>
        <option value="Redtag">Redtag</option>
        <option value="Landmark">Landmark</option>
        <option value="Apparel">Apparel</option>
        <option value="Other">Other</option>
      </select>

      <label>Store:</label>
      <input type="text" name="store" required>

      <label>Location:</label>
      <input type="text" name="location" required>

      <label>Description:</label>
      <textarea name="description" required></textarea>

      <label>Amount:</label>
      <input type="number" step="0.01" name="amount" required>

      <label>Bill:</label>
      <select name="bill" required>
        <option value="">-- Bill --</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>

      <button type="submit" class="btn">Save Room</button>
    </form>
  </div>
</div>

<!-- Tools Popup -->
<div class="popup" id="toolsPopup">
  <div class="popup-content">
    <span class="close-btn" onclick="closePopup('toolsPopup')">&times;</span>
    <h2>Add Tools Expense</h2>
    <form action="add_tools_expense.php" method="POST">
      <label>Date:</label>
      <input type="text" name="date" id="toolsDate" placeholder="dd-mm-yyyy" class="datemandatory" required>

      <label>Region:</label>
      <select name="region" required>
        <option value="">-- Select Region --</option>
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Description:</label>
      <textarea name="description" required></textarea>

      <label>Amount:</label>
      <input type="number" step="0.01" name="amount" required>

      <label>Bill:</label>
      <select name="bill" required>
        <option value="">-- Select Bill --</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>

      <button type="submit" class="btn">Save Tools</button>
    </form>
  </div>
</div>

<!-- Labour Popup -->
<div class="popup" id="labourPopup">
  <div class="popup-content">
    <span class="close-btn" onclick="closePopup('labourPopup')">&times;</span>
    <h2>Add Labour Expense</h2>
    <form action="add_labour_expense.php" method="POST">
      
    <label>Date:</label>
    <input type="text" name="date" id="labourDate" placeholder="dd-mm-yyyy" class="datemandatory" required>


      <label>Division:</label>
      <select name="division" required>
        <option value="">-- Select Division --</option>
        <option value="Sales">Sales</option>
        <option value="Project">Project</option>
        <option value="Service">Service</option>
        <option value="Installation">Installation</option>
      </select>

      <label>Region:</label>
      <select name="region" required>
        <option value="">-- Select Region --</option>
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" required>
        <option value="">-- Select Company --</option>
        <option value="Redtag">Redtag</option>
        <option value="Landmark">Landmark</option>
        <option value="Apparel">Apparel</option>
        <option value="Other">Other</option>
      </select>

      <label>Store:</label>
      <input type="text" name="store" required>

      <label>Location:</label>
      <input type="text" name="location" required>

      <label>Description:</label>
      <textarea name="description" required></textarea>

      <label>Amount:</label>
      <input type="number" step="0.01" name="amount" required>

      <label>Bill:</label>
      <select name="bill" required>
        <option value="">-- Bill --</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>

      <button type="submit" class="btn">Save Labour</button>
    </form>
  </div>
</div>


<!-- Other Popup -->
<div class="popup" id="otherPopup">
  <div class="popup-content">
    <span class="close-btn" onclick="closePopup('otherPopup')">&times;</span>
    <h2>Add Other Expense</h2>
    <form action="add_other_expense.php" method="POST">
      <label>Date:</label>
      <input type="text" name="date" id="otherDate" placeholder="dd-mm-yyyy" class="datemandatory" required>

      <label>Division:</label>
      <select name="division" id="otherDivision" required>
        <option value="">-- Select Division --</option>
        <option value="Sales">Sales</option>
        <option value="Project">Project</option>
        <option value="Service">Service</option>
        <option value="Installation">Installation</option>
        <option value="Recharge">Recharge</option>
      </select>

      <label>Region:</label>
      <select name="region" id="otherRegion" required>
        <option value="">-- Select Region --</option>
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" id="otherCompany" required>
        <option value="">-- Select Company --</option>
        <option value="Redtag">Redtag</option>
        <option value="Landmark">Landmark</option>
        <option value="Apparel">Apparel</option>
        <option value="Other">Other</option>
      </select>

      <label>Store:</label>
      <input type="text" name="store" id="otherStore" required>

      <label>Location:</label>
      <input type="text" name="location" id="otherLocation" required>

      <label>Description:</label>
      <textarea name="description" required></textarea>

      <label>Amount:</label>
      <input type="number" step="0.01" name="amount" required>

      <label>Bill:</label>
      <select name="bill" required>
        <option value="">-- Bill --</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>

      <button type="submit" class="btn">Save Other</button>
    </form>
  </div>
</div>

<!-- Accessories Popup -->
<div class="popup" id="accessoriesPopup">
  <div class="popup-content">
    <span class="close-btn" onclick="closePopup('accessoriesPopup')">&times;</span>
    <h2>Add Accessories Expense</h2>
    <form action="add_accessories.php" method="POST">
      <label> Date : </label>
      <input type="text" name="date" id="accessoriesDate" placeholder="dd-mm-yyy" class="datemandatory" required>

      <label>Division</label>
      <select name ="division" required>
        <option value="">-- Select Division --</option>
        <option value="Sales">Sales</option>
        <option value="Project">Project</option>
        <option value="Service">Service</option>
        <option value="Installation">Installation</option>
      </select>

      <label>Region</label>
      <select name="region" required>
        <option value="">-- Select Region --</option>
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" required>
        <option value="">-- Select Company --</option>
        <option value="Redtag">Redtag</option>
        <option value="Landmark">Landmark</option>
        <option value="Apparel">Apparel</option>
        <option value="Other">Other</option>
      </select>

      <label>Store:</label>
      <input type="text" name="store" required>

      <label>Location:</label>
      <input type="text" name="location" required>

      <label>Description:</label>
      <textarea name="description" required></textarea>

      <label>Amount:</label>
      <input type="number" step="0.01" name="amount" required>

      <label>Bill:</label>
      <select name="bill" required>
        <option value="">-- Select Bill --</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>

      <button type="submit" class="btn">Save Accessories</button>
    </form>
  </div>
</div>

<!-- TV Popup -->
<div class="popup" id="tvPopup">
  <div class="popup-content">
    <span class="close-btn" onclick="closePopup('tvPopup')">&times;</span>
    <h2>Add TV Expense</h2>
    <form action="add_tv.php" method="POST" enctype="multipart/form-data">
      
      <label>Date:</label>
      <input type="date" name="date" id="tvDate" placeholder="dd-mm-yyyy" class="datemandatory" required>

      <label>Division:</label>
      <select name="division" required>
        <option value="">-- Select Division --</option>
        <option value="Sales">Sales</option>
        <option value="Project">Project</option>
        <option value="Service">Service</option>
        <option value="Installation">Installation</option>
      </select>

      <label>Region:</label>
      <select name="region" required>
        <option value="">-- Select Region --</option>
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" required>
        <option value="">-- Select Company --</option>
        <option value="Redtag">Redtag</option>
        <option value="Landmark">Landmark</option>
        <option value="Apparel">Apparel</option>
        <option value="Other">Other</option>
      </select>

      <label>Store:</label>
      <input type="text" name="store" required>

      <label>Location:</label>
      <input type="text" name="location" required>

      <div>
        <label>TV:</label><br>
        <input type="radio" name="tv_type" value="NEW" onclick="toggleTVFields('NEW')" required> New
        <input type="radio" name="tv_type" value="REPAIRED" onclick="toggleTVFields('REPAIRED')"> Repaired
      </div>
      <br>

      <div style="margin-bottom: 10px;">
        <label>Description:</label>
        <textarea name="description" rows="3" style="width:100%;" required></textarea>
      </div>

      <div id="updatedDescriptionField" style="display:none; margin-bottom: 10px;">
        <label>Old TV Description:</label>
        <textarea name="updated_description" rows="3" style="width:100%;"></textarea>
      </div>

      <label>Amount:</label>
      <input type="number" name="amount" step="0.01" required>

      <label>Bill:</label>
      <select name="bill" required>
        <option value="">-- Select Bill --</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>

      <button type="submit" style="margin-top: 10px;">Save</button>
    </form>
  </div>
</div>


<!-- ✅ Vehicle Expense Popup -->
<div class="popup" id="vehiclePopup">
  <div class="popup-content">
    <span class="close-btn" onclick="closePopup('vehiclePopup')">&times;</span>
    <h2>Add Vehicle Expense</h2>
    <form action="add_vehicle_expense.php" method="POST">
      
      <label>Date:</label>
      <input type="date" id="vehicleDate" name="date" class="form-control datemandatory" required>


      <label>Vehicle:</label>
      <select name="vehicle_id" required>
        <option value="" disabled selected>Select a vehicle</option>
        <?php foreach ($vehicles as $vehicle): ?>
          <option value="<?= $vehicle['id'] ?>">
            <?= htmlspecialchars($vehicle['model']) ?> - <?= htmlspecialchars($vehicle['number_plate']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <label>Region:</label>
      <select name="region" required>
        <option value="">-- Select Region--</option>
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Service:</label>
      <select name="service" class="form-select" required>
        <option value="">-- Select Service --</option>
        <option>Engine Oil</option>
        <option>Gear Oil</option>
        <option>Tyre</option>
        <option>Brake Pad</option>
        <option>Brake Oil</option>
        <option>Fuel Injection</option>
        <option>Battery</option>
        <option>Car wash</option>
        <option>Other</option>
      </select>

      <label>KM Reading:</label>
      <input type="number" name="km_reading" required>

      <label>Description:</label>
      <textarea name="description" required></textarea>

      <label>Amount:</label>
      <input type="number" step="0.01" name="amount" required>

      <label>Bill:</label>
      <select name="bill" required>
        <option value="">-- Select Bill --</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>

      <button type="submit" class="btn">Save Vehicle Expense</button>
    </form>
  </div>
</div>

<script>
function openPopup(id){ document.getElementById(id).classList.add('active'); }
function closePopup(id){ document.getElementById(id).classList.remove('active'); }
window.onclick = function(e){
    document.querySelectorAll('.popup').forEach(p=>{
        if(e.target==p) p.classList.remove('active');
    });
}
function toggleTVFields(type) {
  const oldDescField = document.getElementById("updatedDescriptionField");
  const oldDescTextarea = oldDescField.querySelector("textarea");

  if (type === 'NEW') {
    oldDescField.style.display = "block";
    oldDescTextarea.setAttribute("required", "required");
  } else {
    oldDescField.style.display = "none";
    oldDescTextarea.removeAttribute("required");
  }
}


function toggleTVFields(type) {
  const oldDescField = document.getElementById("updatedDescriptionField");
  const oldDescTextarea = oldDescField.querySelector("textarea");

  if (type === 'NEW') {
    oldDescField.style.display = "block";
    oldDescTextarea.setAttribute("required", "required");
  } else {
    oldDescField.style.display = "none";
    oldDescTextarea.removeAttribute("required");
  }
}

//description cant use . or signle character
document.addEventListener("DOMContentLoaded", function () {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const descriptionFields = form.querySelectorAll('textarea[name="description"], textarea[name="updated_description"]');

            for (let field of descriptionFields) {
                const value = field.value.trim();

                // 1. Check for period
                if (value.includes('.')) {
                    alert("Description cannot contain a period (.)");
                    field.focus();
                    e.preventDefault();
                    return false;
                }

                // 2. Check for single-character descriptions (excluding white space)
                const stripped = value.replace(/\s/g, '');
                if (stripped.length < 2) {
                    alert("Description must be at least 2 characters (excluding spaces).");
                    field.focus();
                    e.preventDefault();
                    return false;
                }
            }
        });
    });
});

// Run this on page load in case of edit mode or pre-selected option
window.onload = function() {
  const selectedType = document.querySelector('input[name="tv_type"]:checked');
  if (selectedType) {
    toggleTVFields(selectedType.value);
  }
}

// Example function to close popup (you can adjust this as needed)
function closePopup(popupId) {
  document.getElementById(popupId).style.display = 'none';
}



document.addEventListener('DOMContentLoaded', function () {
    // Function to enable/disable submit buttons based on date input
    function updateButtonState(input) {
        const form = input.closest('form');
        if (!form) return;
        const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (!submitButton) return;

        if (input.value.trim() === '') {
            submitButton.disabled = true;
        } else {
            submitButton.disabled = false;
        }
    }

    // Find all date inputs with class 'datemandatory'
    const dateInputs = document.querySelectorAll('.datemandatory');

    dateInputs.forEach(input => {
        updateButtonState(input); // Initial state on load

        input.addEventListener('input', () => {
            updateButtonState(input);
        });

        input.addEventListener('change', () => {
            updateButtonState(input);
        });
    });
});

    // Attach flatpickr to each unique input
    flatpickr("#fuelDate", { dateFormat: "d-m-Y", maxDate: "today" });
flatpickr("#foodDate", { dateFormat: "d-m-Y", maxDate: "today" });
flatpickr("#roomDate", { dateFormat: "d-m-Y", maxDate: "today" });
flatpickr("#otherDate", { dateFormat: "d-m-Y", maxDate: "today" });
flatpickr("#labourDate", { dateFormat: "d-m-Y", maxDate: "today" });
flatpickr("#accessoriesDate", { dateFormat: "d-m-Y", maxDate: "today" });
flatpickr("#tvDate", { dateFormat: "d-m-Y", maxDate: "today" });
flatpickr("#vehicleDate", { dateFormat: "Y-m-d", maxDate: "today" });


// Validate that the date is not in the future
function isValidDate(dateStr) {
    const [day, month, year] = dateStr.split('-').map(Number);
    const inputDate = new Date(year, month - 1, day);
    const today = new Date();

    return inputDate <= today;
}

// Add listener to each form to validate date
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const dateInput = form.querySelector('input[type="text"][name="date"], input[type="date"][name="date"]');
            if (dateInput) {
                let dateValue = dateInput.value.trim();

                if (!dateValue) {
                    alert("Please enter a date.");
                    dateInput.focus();
                    e.preventDefault();
                    return false;
                }

                // If input is of type "date", convert it to d-m-Y for consistent validation
                if (dateInput.type === "date") {
                    const dateObj = new Date(dateValue);
                    dateValue = ("0" + dateObj.getDate()).slice(-2) + "-" +
                                ("0" + (dateObj.getMonth() + 1)).slice(-2) + "-" +
                                dateObj.getFullYear();
                }

                if (!isValidDate(dateValue)) {
                    alert("The date cannot be in the future.");
                    dateInput.focus();
                    e.preventDefault();
                    return false;
                }
            }
        });
    });
});




    // Popup close function
    function closePopup(id) {
        document.getElementById(id).style.display = "none";
    }

    // Disable/enable fields based on Division selection in Other popup
    const otherDivision = document.getElementById('otherDivision');
    const otherCompany = document.getElementById('otherCompany');
    const otherStore = document.getElementById('otherStore');
    const otherLocation = document.getElementById('otherLocation');

    otherDivision.addEventListener('change', () => {
        if (otherDivision.value === 'Recharge') {
            otherCompany.disabled = true;
            otherStore.disabled = true;
            otherLocation.disabled = true;
        } else {
            otherCompany.disabled = false;
            otherStore.disabled = false;
            otherLocation.disabled = false;
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</body>
</html>
