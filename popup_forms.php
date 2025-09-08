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
      <input type="text" name="date" id="fuelDate" placeholder="dd-mm-yyyy" required>

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
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" required>
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
      <input type="text" name="date" id="foodDate" placeholder="dd-mm-yyyy" required>

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
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" required>
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
      <input type="text" name="date" id="roomDate" placeholder="dd-mm-yyyy" required>

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
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" required>
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
      <input type="text" name="date" id="toolsDate" placeholder="dd-mm-yyyy" required>

      <label>Region:</label>
      <select name="region" required>
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
    <input type="text" name="date" id="labourDate" placeholder="dd-mm-yyyy" required>


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
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" required>
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
      <input type="text" name="date" id="otherDate" placeholder="dd-mm-yyyy" required>

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
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" id="otherCompany" required>
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
      <input type="text" name="date" id="accessoriesDate" placeholder="dd-mm-yyy" required>

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
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" required>
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
      
      <label> Date : </label>
      <input type="date" name="date" id="tvDate" placeholder="dd-mm-yyy" required>

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
        <option value="">-- Select Region--</option>
        <option value="Dammam">Dammam</option>
        <option value="Riyadh">Riyadh</option>
        <option value="Jeddah">Jeddah</option>
        <option value="Other">Other</option>
      </select>

      <label>Company:</label>
      <select name="company" required>
        <option value="">-- Select company --</option>
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

      <!-- Description Fields -->
      <div style="margin-bottom: 10px;">
        <label>Description:</label>
        <textarea name="description" rows="3" style="width:100%;" required></textarea>
      </div>

      <div id="updatedDescriptionField" style="display:none; margin-bottom: 10px;">
        <label> Old TV Description:</label>
        <textarea name="updated_description" rows="3" style="width:100%;"></textarea>
      </div>

      <label>Amount:</label>
      <input type="number" name="amount" step="0.01" required>

      <label>Bill:</label>
      <select name="bill" required>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>

      <button type="submit" style="margin-top: 10px;">Save</button>
    </form>
  </div>
</div>

<script>
function toggleTVFields(type) {
  if (type === 'NEW') {
    document.getElementById("updatedDescriptionField").style.display = "block";
  } else {
    document.getElementById("updatedDescriptionField").style.display = "none";
  }
}
</script>

<script>
function toggleTVFields(type) {
  if (type === 'NEW') {
    document.getElementById("updatedDescriptionField").style.display = "block";
  } else {
    document.getElementById("updatedDescriptionField").style.display = "none";
  }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    // Attach flatpickr to each unique input
    flatpickr("#fuelDate", { dateFormat: "d-m-Y" });
    flatpickr("#foodDate", { dateFormat: "d-m-Y" });
    flatpickr("#roomDate", { dateFormat: "d-m-Y" });
    flatpickr("#otherDate", { dateFormat: "d-m-Y" });
    flatpickr("#labourDate", { dateFormat: "d-m-Y" });
    flatpickr("#accessoriesDate", { dateFormat: "d-m-Y" });
    flatpickr("#tvDate", { dateFormat: "d-m-Y" });


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

</body>
</html>
