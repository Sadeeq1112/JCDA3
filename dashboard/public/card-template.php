<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Membership Card</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <link rel="stylesheet" href="../assets/css/card.css">

    <style>
        .detail-label {
            text-wrap: nowrap !important;
        }

        .detail-value {
            text-wrap: nowrap !important;
        }
    </style>
</head>

<body>
    <div>
        <div class="card-container" id="card-container">
            <!-- Front Card -->
            <div class="membership-card" style="margin-right: 40px;">
                <div class="card-front" style="margin-top: 110px;height: 415px;">
                    <img src="<?php echo isset($_POST['profile_picture']) ? htmlspecialchars($_POST['profile_picture']) : '...'; ?>"
                        alt="Member Photo" class="member-photo" style="margin-bottom: 0px;">

                    <div class="card-details" style="margin-top: 2px;">
                        <div style="width: 290px; margin: 0 auto; text-align: center;">
                            <h3 class="auto-resize-text"
                                style="margin: 0; font-family: Inter; white-space: nowrap;text-align: center;font-weight: 600">
                                <?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : 'No Name Provided'; ?>
                            </h3>
                        </div>

                        <script>
                            function adjustTextSize(element) {
                                const container = element.parentElement;
                                let fontSize = 30; // Starting font size in px
                                element.style.fontSize = fontSize + 'px';

                                // Reduce font size until text fits or reaches minimum
                                while (element.scrollWidth > container.offsetWidth && fontSize > 10) {
                                    fontSize--;
                                    element.style.fontSize = fontSize + 'px';
                                }
                            }

                            // Run on load and window resize
                            window.addEventListener('load', function () {
                                const textElements = document.querySelectorAll('.auto-resize-text');
                                textElements.forEach(adjustTextSize);
                            });

                            window.addEventListener('resize', function () {
                                const textElements = document.querySelectorAll('.auto-resize-text');
                                textElements.forEach(adjustTextSize);
                            });
                        </script>
                        <h3
                            style="margin: 0;text-align: center;font-family: Inter;font-size: 14px;font-weight: 500;margin-top: 0px;">
                            <?php echo isset($_POST['occupation']) ? htmlspecialchars($_POST['occupation']) : 'N/A'; ?>
                        </h3>

                        <div class="detail-row" style="margin-top: 18px;">
                            <span class="detail-label">Membership ID</span>
                            <span id="member_id" class="detail-value"
                                style=""><?php echo isset($_POST['membership_no']) ? htmlspecialchars($_POST['membership_no']) : 'N/A'; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Issue Date</span>
                            <span class="detail-value">
                                <?php
                                echo isset($_POST['card_issue_date']) && !empty($_POST['card_issue_date'])
                                    ? htmlspecialchars(date('d/m/Y', strtotime($_POST['card_issue_date'])))
                                    : 'N/A';
                                ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Expiry Date</span>
                            <span class="detail-value">
                                <?php
                                echo isset($_POST['card_expiry_date']) && !empty($_POST['card_expiry_date'])
                                    ? htmlspecialchars(date('d/m/Y', strtotime($_POST['card_expiry_date'])))
                                    : 'N/A';
                                ?>
                            </span>
                        </div>
                        <div id="barcode-output"
                            style="justify-content: center;display: flex;height: 50px;overflow: hidden;margin-top: 0px;padding-top: 0px;">
                        </div>
                    </div>
                </div>
            </div>

            <div class="membership-card-back">
                <div id="qrcode-output"
                    style="display: flex;justify-content: end;margin-top: 423px;margin-right: 20px;">
                </div>
            </div>
        </div>

        <div style="display: flex;justify-content: center;margin-top: 30px;gap: 20px;z-index:99999">
            <button type="button" class="btn btn-primary" id="print-btn">Print</button>
            <button type="button" id="download-btn" class="btn btn-primary">Download As Image</button>
        </div>
        <div style="display: flex;justify-content: center;margin-top: 10px;gap: 20px;">
            <button type="button" onclick="location.href='card.php'" class="btn btn-secondary">Go Back To
                Dashboard</button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/dom-to-image/2.6.0/dom-to-image.min.js"></script>

    <script>
        document.getElementById('print-btn').addEventListener('click', function () {
            const btn = this;
            const originalText = btn.textContent;

            // Show printing state
            btn.disabled = true;
            btn.textContent = 'Preparing print...';

            // Create a print stylesheet dynamically
            const printStyle = document.createElement('style');
            printStyle.textContent = `
    @media print {
      body * {
        visibility: hidden;
      }
      #card-container,
      #card-container * {
        visibility: visible;
      }
      #card-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
      }
    }
  `;
            document.head.appendChild(printStyle);

            // Trigger print dialog
            window.print();

            // Clean up after printing
            setTimeout(() => {
                document.head.removeChild(printStyle);
                btn.textContent = 'Print Complete!';
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }, 2000);
            }, 100);
        });
    </script>
    <script>
        document.getElementById('download-btn').addEventListener('click', function () {
            const btn = this;
            const originalText = btn.textContent;

            // Show generating state
            btn.disabled = true;
            btn.textContent = 'Generating...';

            const node = document.getElementById('card-container');

            domtoimage.toPng(node, {
                quality: 1,
                bgcolor: '#fff',
                width: node.offsetWidth * 2,
                height: node.offsetHeight * 2,
                style: {
                    'transform': 'scale(2)',
                    'transform-origin': 'top left'
                }
            })
                .then(function (dataUrl) {
                    // Create and trigger download
                    const link = document.createElement('a');
                    link.download = 'jcda_card.png';
                    link.href = dataUrl;
                    link.click();

                    // Show success state
                    btn.textContent = 'Success!';

                    // Revert to original text after 2 seconds
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }, 2000);
                })
                .catch(function (error) {
                    console.error('Error:', error);
                    btn.textContent = 'Error! Try Again';
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }, 2000);
                });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            try {
                // 1. Get the member ID
                const memberIdElement = document.getElementById('member_id');
                if (!memberIdElement) {
                    throw new Error("Member ID element not found");
                }

                const memberId = memberIdElement.textContent.trim();
                if (!memberId) {
                    throw new Error("Member ID is empty");
                }

                // 2. Prepare the container
                const outputDiv = document.getElementById('barcode-output');
                if (!outputDiv) {
                    throw new Error("Barcode output div not found");
                }

                // 3. Clear previous content and create SVG
                outputDiv.innerHTML = '<svg id="barcode-svg"></svg>';

                // 4. Generate the barcode
                JsBarcode("#barcode-svg", memberId, {
                    format: "CODE39",
                    lineColor: "#000000",
                    width: 2,
                    height: 60,
                    displayValue: true,
                    fontSize: 16,
                    margin: 10
                });

                console.log("Barcode generated successfully!");

            } catch (error) {
                console.error("Barcode generation failed:", error.message);
                // Display error message to user
                const outputDiv = document.getElementById('barcode-output') || document.body;
                outputDiv.innerHTML = `<div style="color: red;">Error: ${error.message}</div>`;
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            try {
                // 1. Get the member ID
                const memberIdElement = document.getElementById('member_id');
                if (!memberIdElement) {
                    throw new Error("Member ID element not found");
                }

                const memberId = memberIdElement.textContent.trim();
                if (!memberId) {
                    throw new Error("Member ID is empty");
                }

                // 2. Prepare the container
                const outputDiv = document.getElementById('qrcode-output');
                if (!outputDiv) {
                    throw new Error("QR code output div not found");
                }

                // 3. Generate QR code
                const qr = qrcode(0, 'L'); // L = Low error correction level
                qr.addData(memberId);
                qr.make();

                // 4. Create QR code image element
                const qrImg = document.createElement('img');
                qrImg.id = 'qrcode-canvas';
                qrImg.src = qr.createDataURL(4, 10); // 4 = module size, 10 = margin
                qrImg.alt = 'QR Code for ' + memberId;

                // 5. Add to container
                outputDiv.innerHTML = ''; // Clear previous content
                outputDiv.appendChild(qrImg);

                console.log("QR code generated successfully!");

            } catch (error) {
                console.error("QR code generation failed:", error.message);
                const outputDiv = document.getElementById('qrcode-output') || document.body;
                outputDiv.innerHTML = `<div style="color: red;">Error: ${error.message}</div>`;
            }
        });
    </script>
</body>

</html>