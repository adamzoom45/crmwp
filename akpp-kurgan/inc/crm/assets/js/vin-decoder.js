/* VIN декодер стили */
.vin-decode-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.vin-input-group {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.vin-input-group .form-field {
    flex: 1;
}

#decode-vin-btn {
    height: 42px;
    padding: 0 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

#decode-vin-btn .spinner {
    float: none;
    margin-top: 0;
    vertical-align: middle;
}

.vin-fields {
    margin-top: 20px;
    padding: 15px;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.vin-fields .row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.vin-fields .form-field {
    flex: 1;
    min-width: 150px;
}

.vin-fields label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    font-size: 13px;
    color: #495057;
}

.vin-fields input,
.vin-fields select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}

.vin-fields input:focus,
.vin-fields select:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

#market-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    margin-left: 10px;
}

.market-japan {
    background: #fff3e0;
    color: #e67e22;
}

.market-asia {
    background: #e8f5e9;
    color: #4caf50;
}

.market-europe {
    background: #e3f2fd;
    color: #2196f3;
}

.market-usa {
    background: #fce4ec;
    color: #e91e63;
}

#manual-entry-warning {
    margin-top: 15px;
    padding: 10px 15px;
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    border-radius: 4px;
}

#vin-message {
    margin: 15px 0;
    padding: 10px 15px;
    border-radius: 4px;
}

#vin-message.notice-success {
    background: #d4edda;
    border-left: 4px solid #28a745;
    color: #155724;
}

#vin-message.notice-error {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
    color: #721c24;
}

.ui-autocomplete {
    max-height: 200px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    z-index: 10000;
}

.ui-autocomplete li {
    padding: 8px 12px;
    cursor: pointer;
    list-style: none;
}

.ui-autocomplete li:hover {
    background: #f0f2f5;
}
