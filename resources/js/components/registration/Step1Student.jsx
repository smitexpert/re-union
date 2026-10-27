import ImageInput from '@/components/registration/fields/ImageInput';
import SelectInput from '@/components/registration/fields/SelectInput';
import TextInput from '@/components/registration/fields/TextInput';
import TextAreaInput from '@/components/registration/fields/TextAreaInput';

const TSHIRT_SIZES = ['S', 'M', 'L', 'XL', 'XXL'];

export default function Step1Student({ data, setData, errors, options }) {
    const updateStudent = (field) => (e) => setData(`student.${field}`, e.target.value);
    const updateTop = (field) => (e) => setData(field, e.target.value);

    return (
        <div className="space-y-4">
            <h2 id="step-1-heading" className="text-xl font-bold text-gray-900">
                Student information
            </h2>
            <p className="text-sm text-gray-600">
                Tell us about yourself. We'll use this to register you for the reunion.
            </p>

            <ImageInput
                id="student.photo"
                label="Your photo"
                value={data.student.photo}
                onChange={(file) => setData('student.photo', file)}
                error={errors['student.photo']}
                required
            />
            <TextInput
                id="student.full_name"
                label="Full name"
                value={data.student.full_name}
                onChange={updateStudent('full_name')}
                error={errors['student.full_name']}
                autoComplete="name"
                required
            />
            <TextInput
                id="student.phone"
                type="tel"
                label="Phone"
                value={data.student.phone}
                onChange={updateStudent('phone')}
                error={errors['student.phone']}
                autoComplete="tel"
                required
            />
            <div className="grid grid-cols-2 gap-3">
                <TextInput
                    id="student.graduation_year"
                    type="number"
                    label="Graduation year"
                    inputMode="numeric"
                    value={data.student.graduation_year}
                    onChange={updateStudent('graduation_year')}
                    error={errors['student.graduation_year']}
                    required
                />
                <SelectInput
                    id="student.option"
                    label="Option"
                    options={options}
                    placeholder="Select option…"
                    value={data.student.option}
                    onChange={updateStudent('option')}
                    error={errors['student.option']}
                    required
                />
            </div>
            <TextInput
                id="student.session"
                label="Session"
                value={data.student.session}
                onChange={updateStudent('session')}
                error={errors['student.session']}
                placeholder="e.g. 2010-11"
                required
            />
            <TextInput
                id="student.current_occupation"
                label="Current occupation"
                value={data.student.current_occupation}
                onChange={updateStudent('current_occupation')}
                error={errors['student.current_occupation']}
                required
            />
            <TextAreaInput
                id="student.present_address"
                label="Present address"
                value={data.student.present_address}
                onChange={updateStudent('present_address')}
                error={errors['student.present_address']}
                required
            />
            <TextAreaInput
                id="student.permanent_address"
                label="Permanent address"
                value={data.student.permanent_address}
                onChange={updateStudent('permanent_address')}
                error={errors['student.permanent_address']}
            />
            <TextAreaInput
                id="student.office_address"
                label="Office / business address"
                value={data.student.office_address}
                onChange={updateStudent('office_address')}
                error={errors['student.office_address']}
            />

            <SelectInput
                id="tshirt_size"
                label="T-shirt size"
                options={TSHIRT_SIZES}
                placeholder="Select size…"
                value={data.tshirt_size}
                onChange={updateTop('tshirt_size')}
                error={errors.tshirt_size}
                required
            />
        </div>
    );
}
