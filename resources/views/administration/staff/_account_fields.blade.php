<input type="hidden" name="facility_id" value="{{ $facility->id }}">
<div class="form-grid">
    <label class="field"><span>Full name</span><input name="name" value="{{ old('name', $staff?->name) }}" maxlength="255" required autocomplete="name"></label>
    <label class="field"><span>Email address</span><input type="email" name="email" value="{{ old('email', $staff?->email) }}" maxlength="255" required autocomplete="email"></label>
    <label class="field"><span>Employee number</span><input name="employee_number" value="{{ old('employee_number', $staff?->staffProfile?->employee_number) }}" maxlength="50"></label>
    <label class="field"><span>Job title</span><input name="job_title" value="{{ old('job_title', $staff?->staffProfile?->job_title) }}" maxlength="255"></label>
    <label class="field"><span>Department</span><select name="department_id" required><option value="">Select department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string) old('department_id', $staff?->staffProfile?->department_id) === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></label>
    <label class="field"><span>Service point</span><select name="service_point_id"><option value="">No service point</option>@foreach($servicePoints as $servicePoint)<option value="{{ $servicePoint->id }}" data-department="{{ $servicePoint->department_id }}" @selected((string) old('service_point_id', $staff?->staffProfile?->service_point_id) === (string) $servicePoint->id)>{{ $servicePoint->department?->name ? $servicePoint->department->name.' · ' : '' }}{{ $servicePoint->name }}</option>@endforeach</select></label>
    <label class="field"><span>Phone</span><input name="phone" value="{{ old('phone', $staff?->staffProfile?->phone) }}" maxlength="40" autocomplete="tel"></label>
    <label class="field"><span>Joined date</span><input type="date" name="joined_at" value="{{ old('joined_at', $staff?->staffProfile?->joined_at?->toDateString()) }}" max="{{ today()->toDateString() }}"></label>
</div>
